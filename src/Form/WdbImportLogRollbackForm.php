<?php

namespace Drupal\wdb_core\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\wdb_core\Entity\WdbImportLog;

/**
 * Provides a confirmation form for rolling back a WDB import job.
 */
class WdbImportLogRollbackForm extends ConfirmFormBase {

  /**
   * The WDB Import Log entity to be rolled back.
   *
   * @var \Drupal\wdb_core\Entity\WdbImportLog
   */
  protected $importLog;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wdb_core_import_log_rollback_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to roll back the import job "@label"?', ['@label' => $this->importLog->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.wdb_import_log.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $summary = $this->importLog->get('summary')->value;
    return $this->t('This action will attempt to delete all entities created during this import job. This operation cannot be undone. <br><strong>Summary:</strong> @summary', ['@summary' => $summary]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Rollback');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?WdbImportLog $wdb_import_log = NULL) {
    $this->importLog = $wdb_import_log;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $created_entities_json = $this->importLog->get('created_entities')->value;
    $created_entities = json_decode($created_entities_json, TRUE);

    if (empty($created_entities) || !is_array($created_entities)) {
      $this->messenger()->addWarning($this->t('There are no created entities recorded in this log to roll back.'));
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
    }

    // Process entities in reverse order of creation for safer deletion.
    $operations = [];
    foreach (array_reverse($created_entities) as $entity_info) {
      $operations[] = [
        '\Drupal\wdb_core\Form\WdbImportLogRollbackForm::batchDeleteEntity',
        [$entity_info],
      ];
    }

    $batch = [
      'title' => $this->t('Rolling back import job: @label', [
        '@label' => $this->importLog->label(),
      ]),
      'operations' => $operations,
      'finished' => '\Drupal\\wdb_core\\Form\\WdbImportLogRollbackForm::batchFinishedCallback',
      'wdb_import_log_id' => $this->importLog->id(),
      'init_message' => $this->t('Starting rollback process.'),
      'progress_message' => $this->t('Processed @current out of @total entities for deletion.'),
      'error_message' => $this->t('An error occurred during the rollback process.'),
    ];

    batch_set($batch);
  }

  /**
   * Batch API operation for deleting a single entity.
   *
   * @param array $entity_info
   *   An array containing the 'type' and 'id' of the entity to delete.
   * @param array $context
   *   The batch API context array.
   */
  public static function batchDeleteEntity(array $entity_info, array &$context) {
    if (empty($entity_info['type']) || empty($entity_info['id'])) {
      return;
    }

    if (!isset($context['results']['deleted'])) {
      $context['results']['deleted'] = 0;
      $context['results']['failed'] = 0;
      $context['results']['not_found'] = 0;
      $context['results']['errors'] = [];
    }

    $storage = \Drupal::entityTypeManager()->getStorage($entity_info['type']);
    $entity = $storage->load($entity_info['id']);

    if ($entity) {
      try {
        $entity->delete();
        $context['results']['deleted']++;
      }
      catch (\Exception $e) {
        $context['results']['failed']++;
        $context['results']['errors'][] = t('Failed to delete @type with ID @id: @message', [
          '@type' => $entity_info['type'],
          '@id' => $entity_info['id'],
          '@message' => $e->getMessage(),
        ]);
      }
    }
    else {
      $context['results']['not_found']++;
    }
  }

  /**
   * Batch API finished callback for the rollback process.
   *
   * @param bool $success
   *   A boolean indicating whether the batch operation was successful.
   * @param array $results
   *   An array of results collected during the batch operation.
   * @param array $operations
   *   An array of the operations that were performed.
   */
  public static function batchFinishedCallback(bool $success, array $results, array $operations) {
    $batch = &batch_get();
    $log_id = $batch['sets'][0]['wdb_import_log_id'] ?? NULL;

    if (!$log_id) {
      \Drupal::messenger()->addError(t('Could not identify the import log to update after rollback.'));
      return;
    }

    $messenger = \Drupal::messenger();
    $logger = \Drupal::logger('wdb_rollback');

    if ($success) {
      $deleted_count = $results['deleted'] ?? 0;
      $failed_count = $results['failed'] ?? 0;

      $messenger->addStatus(t('Rollback completed. Successfully deleted @count entities.', ['@count' => $deleted_count]));
      if ($failed_count > 0) {
        $messenger->addError(t('@count entities could not be deleted. See logs for details.', ['@count' => $failed_count]));
      }
      if (!empty($results['errors'])) {
        foreach ($results['errors'] as $error) {
          $logger->error($error);
        }
      }

      // Update the import log status.
      try {
        $log_storage = \Drupal::entityTypeManager()->getStorage('wdb_import_log');
        $log = $log_storage->load($log_id);

        if ($log) {
          /** @var \Drupal\wdb_core\Entity\WdbImportLog $log */
          // Mark the log as rolled back.
          $log->set('status', FALSE);
          $log->set('summary', t('ROLLED BACK on @date. (@count entities deleted)', [
            '@date' => \Drupal::service('date.formatter')->format(time()),
            '@count' => $results['deleted'] ?? 0,
          ]));
          // Clear the list of created entities to prevent a second rollback.
          $log->set('created_entities', '[]');
          $log->save();
          $messenger->addStatus(t('The import log for "@label" has been updated.', ['@label' => $log->label()]));
        }
        else {
          $logger->error('Could not load log entity @log_id for update after rollback.', ['@log_id' => $log_id]);
        }
      }
      catch (\Exception $e) {
        $logger->error(
          'Exception thrown while updating log entity @log_id: @message',
          [
            '@log_id' => $log_id,
            '@message' => $e->getMessage(),
          ]
        );
        $messenger->addError(t('Failed to update the import log.'));
      }

    }
    else {
      $messenger->addError(t('An error occurred during the rollback process. The log has not been updated.'));
    }
  }

}
