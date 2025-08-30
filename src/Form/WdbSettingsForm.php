<?php

namespace Drupal\wdb_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure WDB Core settings for this site.
 *
 * This form dynamically generates configuration tabs for each "subsystem"
 * defined in the 'subsystem' taxonomy vocabulary.
 */
class WdbSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wdb_core_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    // Dynamically build a list of configuration object names to be managed by
    // this form, one for each subsystem term.
    $config_names = [];
    $subsystem_terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'subsystem']);
    foreach ($subsystem_terms as $term) {
      $config_names[] = 'wdb_core.subsystem.' . $term->id();
    }
    return $config_names;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity_type_manager = \Drupal::entityTypeManager();

    $form['vertical_tabs'] = ['#type' => 'vertical_tabs'];

    // --- Dynamically generate a settings tab for each subsystem ---
    $term_storage = $entity_type_manager->getStorage('taxonomy_term');

    // --- FIX: Sort by term name (alphabetically) instead of weight. ---
    $tids = $term_storage->getQuery()
      ->condition('vid', 'subsystem')
      ->sort('name')
      ->accessCheck(FALSE)
      ->execute();
    // --- END OF FIX ---
    $subsystem_terms = $term_storage->loadMultiple($tids);

    $form['subsystems'] = ['#type' => 'container', '#tree' => TRUE];

    foreach ($subsystem_terms as $term_id => $term) {
      $config_name = 'wdb_core.subsystem.' . $term_id;
      $config = $this->config($config_name);

      $form['subsystems'][$term_id] = [
        '#type' => 'details',
        '#title' => $term->label(),
        '#group' => 'vertical_tabs',
      ];

      $form['subsystems'][$term_id]['display_title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Display Title'),
        '#description' => $this->t('The title to be displayed for this subsystem in a block.'),
        '#default_value' => $config->get('display_title'),
        '#maxlength' => 255,
      ];

      $form['subsystems'][$term_id]['display_title_link'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Display Title Link URL'),
        '#description' => $this->t('If you want to link the subsystem title, enter the URL here. You can use an internal path (e.g., /node/1) or a full external URL (e.g., https://example.com). Leave blank for no link.'),
        '#default_value' => $config->get('display_title_link'),
      ];

      $form['subsystems'][$term_id]['iiif_settings'] = [
        '#type' => 'details',
        '#title' => $this->t('IIIF Settings'),
        '#open' => TRUE,
      ];
      $form['subsystems'][$term_id]['iiif_settings']['iiif_server_scheme'] = [
        '#type' => 'select',
        '#title' => $this->t('IIIF Server Scheme'),
        '#options' => ['http' => 'http', 'https' => 'https'],
        '#default_value' => $config->get('iiif_server_scheme'),
      ];
      $form['subsystems'][$term_id]['iiif_settings']['iiif_server_hostname'] = [
        '#type' => 'textfield',
        '#title' => $this->t('IIIF Server Hostname'),
        '#default_value' => $config->get('iiif_server_hostname'),
        '#description' => $this->t('Do not include slashes at the beginning or end.'),
      ];
      $form['subsystems'][$term_id]['iiif_settings']['iiif_server_prefix'] = [
        '#type' => 'textfield',
        '#title' => $this->t('IIIF Server Prefix'),
        '#default_value' => $config->get('iiif_server_prefix'),
        '#description' => $this->t('Do not include slashes at the beginning or end. No URL encoding is required.'),
      ];
      $form['subsystems'][$term_id]['iiif_settings']['iiif_fileExt'] = [
        '#type' => 'textfield',
        '#title' => $this->t('IIIF Image File Extension'),
        '#default_value' => $config->get('iiif_fileExt'),
        '#description' => $this->t('Do not include the leading dot (e.g., "jpg", not ".jpg").'),
      ];
      $form['subsystems'][$term_id]['iiif_settings']['iiif_license'] = [
        '#type' => 'textfield',
        '#title' => $this->t('IIIF License URL'),
        '#default_value' => $config->get('iiif_license'),
      ];
      $form['subsystems'][$term_id]['iiif_settings']['iiif_attribution'] = [
        '#type' => 'textfield',
        '#title' => $this->t('IIIF Attribution Text'),
        '#default_value' => $config->get('iiif_attribution'),
      ];
      $form['subsystems'][$term_id]['iiif_settings']['iiif_logo'] = [
        '#type' => 'textfield',
        '#title' => $this->t('IIIF Logo URL'),
        '#default_value' => $config->get('iiif_logo'),
      ];

      $form['subsystems'][$term_id]['iiif_settings']['iiif_identifier_pattern'] = [
        '#type' => 'textfield',
        '#title' => $this->t('IIIF Identifier Pattern'),
        '#default_value' => $config->get('iiif_identifier_pattern'),
        '#description' => $this->t('Define a pattern to automatically generate image identifiers. Use placeholders: <code>{source_identifier}</code>, <code>{page_number}</code>, <code>{page_name}</code>, <code>{subsystem_name}</code>.'),
        '#placeholder' => '{source_identifier}/{page_number}.tif',
      ];

      if (!empty($config->get('iiif_identifier_pattern'))) {
        $form['subsystems'][$term_id]['iiif_settings']['reapply_pattern'] = [
          '#type' => 'details',
          '#title' => $this->t('Update Existing Pages'),
          '#description' => $this->t('If you have changed the IIIF Identifier Pattern, you can apply the new pattern to all existing annotation pages within this subsystem. This will overwrite any manually set identifiers.'),
        ];
        $form['subsystems'][$term_id]['iiif_settings']['reapply_pattern']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Apply pattern to existing pages in "@subsystem"', ['@subsystem' => $term->label()]),
          '#submit' => ['::submitReapplyPattern'],
          '#subsystem_id' => $term_id,
        ];
      }

      $form['subsystems'][$term_id]['allowAnonymous'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Allow anonymous access'),
        '#default_value' => $config->get('allowAnonymous'),
      ];
      $form['subsystems'][$term_id]['pageNavigation'] = [
        '#type' => 'select',
        '#title' => $this->t('Page Navigation Direction'),
        '#options' => ['left-to-right' => 'Left-to-Right', 'right-to-left' => 'Right-to-Left'],
        '#default_value' => $config->get('pageNavigation'),
      ];
      $form['subsystems'][$term_id]['hullConcavity'] = [
        '#type' => 'number',
        '#title' => $this->t('Hull Concavity'),
        '#description' => $this->t('A higher value creates a more detailed polygon. Set to 0 for a convex hull.'),
        '#default_value' => $config->get('hullConcavity'),
      ];

      $form['subsystems'][$term_id]['export_templates'] = [
        '#type' => 'details',
        '#title' => $this->t('Export Templates'),
        '#open' => FALSE,
      ];
      $form['subsystems'][$term_id]['export_templates']['tei'] = [
        '#type' => 'textarea',
        '#title' => $this->t('TEI/XML Template'),
        '#rows' => 15,
        '#default_value' => $config->get('export_templates.tei'),
      ];
      $form['subsystems'][$term_id]['export_templates']['rdf'] = [
        '#type' => 'textarea',
        '#title' => $this->t('RDF/XML Template'),
        '#rows' => 15,
        '#default_value' => $config->get('export_templates.rdf'),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $subsystems_values = $form_state->getValue('subsystems');
    if (is_array($subsystems_values)) {
      foreach ($subsystems_values as $term_id => $values) {
        if (!is_numeric($term_id)) {
          continue;
        }

        $config_name = 'wdb_core.subsystem.' . $term_id;
        $this->config($config_name)
          ->set('display_title', $values['display_title'])
          ->set('display_title_link', $values['display_title_link'])
          ->set('allowAnonymous', $values['allowAnonymous'])
          ->set('pageNavigation', $values['pageNavigation'])
          ->set('hullConcavity', $values['hullConcavity'])
          ->set('iiif_server_scheme', $values['iiif_settings']['iiif_server_scheme'])
          ->set('iiif_server_hostname', $values['iiif_settings']['iiif_server_hostname'])
          ->set('iiif_server_prefix', $values['iiif_settings']['iiif_server_prefix'])
          ->set('iiif_fileExt', $values['iiif_settings']['iiif_fileExt'])
          ->set('iiif_license', $values['iiif_settings']['iiif_license'])
          ->set('iiif_attribution', $values['iiif_settings']['iiif_attribution'])
          ->set('iiif_logo', $values['iiif_settings']['iiif_logo'])
          ->set('iiif_identifier_pattern', $values['iiif_settings']['iiif_identifier_pattern'])
          ->set('export_templates.tei', $values['export_templates']['tei'])
          ->set('export_templates.rdf', $values['export_templates']['rdf'])
          ->save();
      }
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Submit handler for the "Apply pattern" button.
   */
  public function submitReapplyPattern(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $subsystem_id = $triggering_element['#subsystem_id'];

    $operations = [
      [
        '\Drupal\wdb_core\Form\WdbSettingsForm::batchProcessReapplyPattern',
        [$subsystem_id],
      ],
    ];

    $batch = [
      'title' => $this->t('Applying new identifier pattern...'),
      'operations' => $operations,
      'finished' => '\Drupal\wdb_core\Form\WdbSettingsForm::batchFinishedCallback',
    ];

    batch_set($batch);
  }

  /**
   * Batch API operation for reapplying the identifier pattern.
   */
  public static function batchProcessReapplyPattern($subsystem_id, &$context) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $source_storage = $entity_type_manager->getStorage('wdb_source');
    $page_storage = $entity_type_manager->getStorage('wdb_annotation_page');

    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $source_ids = $source_storage->getQuery()
        ->condition('subsystem_tags', $subsystem_id)
        ->accessCheck(FALSE)
        ->execute();

      $page_ids = [];
      if (!empty($source_ids)) {
        $page_ids = $page_storage->getQuery()
          ->condition('source_ref', $source_ids, 'IN')
          ->accessCheck(FALSE)
          ->execute();
      }

      $context['sandbox']['page_ids'] = array_values($page_ids);
      $context['sandbox']['max'] = count($page_ids);
      $context['results']['updated'] = 0;
    }

    $page_ids_chunk = array_slice($context['sandbox']['page_ids'], $context['sandbox']['progress'], 10);

    if (empty($page_ids_chunk)) {
      $context['finished'] = 1;
      return;
    }

    $pages_to_update = $page_storage->loadMultiple($page_ids_chunk);
    foreach ($pages_to_update as $page) {
      // The getImageIdentifier() method contains the logic to generate the new
      // identifier from the pattern. We save the entity to store this new value.
      $new_identifier = $page->getImageIdentifier(TRUE);
      $page->set('image_identifier', $new_identifier);
      $page->save();
      $context['results']['updated']++;
    }

    $context['sandbox']['progress'] += count($page_ids_chunk);
    $context['message'] = t('Updating page @progress of @total...', [
      '@progress' => $context['sandbox']['progress'],
      '@total' => $context['sandbox']['max'],
    ]);

    if ($context['sandbox']['progress'] >= $context['sandbox']['max']) {
      $context['finished'] = 1;
    }
    else {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Batch API finished callback.
   */
  public static function batchFinishedCallback($success, $results, $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $updated_count = $results['updated'] ?? 0;
      $messenger->addStatus(\Drupal::translation()->formatPlural(
        $updated_count,
        'Successfully updated 1 annotation page.',
        'Successfully updated @count annotation pages.'
      ));
    }
    else {
      $messenger->addError(t('An error occurred during the update process.'));
    }
  }

}
