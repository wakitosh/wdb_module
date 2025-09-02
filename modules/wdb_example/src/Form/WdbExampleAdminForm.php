<?php

namespace Drupal\wdb_example\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\wdb_example\Service\WdbExampleManager;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Admin UI for WDB Example management.
 */
class WdbExampleAdminForm extends FormBase implements ContainerInjectionInterface {

  /**
   * Example manager service.
   *
   * @var \Drupal\wdb_example\Service\WdbExampleManager
   */
  protected WdbExampleManager $manager;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->manager = $container->get('wdb_example.manager');
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wdb_example_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->configFactory->getEditable('wdb_example.settings');
    $purge_on_uninstall = (bool) $settings->get('purge_on_uninstall');
    $counts = $this->manager->getMapCounts();

    $form['status'] = [
      '#type' => 'details',
      '#title' => $this->t('Current status'),
      '#open' => TRUE,
    ];
    $form['status']['purge_on_uninstall'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Purge example data on uninstall'),
      '#default_value' => $purge_on_uninstall,
      '#description' => $this->t('If enabled, tracked example entities will be deleted when this module is uninstalled.'),
    ];

    $summary = $this->t('Tracked entities in map: @total', ['@total' => $counts['total'] ?? 0]);
    if (!empty($counts['by_type'])) {
      $pieces = [];
      foreach ($counts['by_type'] as $type => $n) {
        $pieces[] = $type . '=' . $n;
      }
      $summary .= ' (' . implode(', ', $pieces) . ')';
    }
    $form['status']['map_summary'] = [
      '#markup' => '<p>' . $summary . '</p>',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['mark'] = [
      '#type' => 'submit',
      '#value' => $this->t('Mark existing samples'),
      '#submit' => ['::submitMark'],
    ];
    $form['actions']['wipe_mark'] = [
      '#type' => 'submit',
      '#value' => $this->t('Wipe & Mark'),
      '#submit' => ['::submitWipeMark'],
    ];
    $form['actions']['purge_now'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run Purge now'),
      '#submit' => ['::submitGoPurgeConfirm'],
      '#attributes' => ['class' => ['button--danger']],
    ];

    $form['#submit'][] = '::saveSettings';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) : void {
    // No-op. Specific actions are handled by dedicated submit handlers.
  }

  /**
   * Submit handler to save settings.
   */
  public function saveSettings(array &$form, FormStateInterface $form_state): void {
    $settings = $this->configFactory->getEditable('wdb_example.settings');
    $settings->set('purge_on_uninstall', (bool) $form_state->getValue('purge_on_uninstall'))
      ->save();
    $this->messenger()->addStatus($this->t('Settings saved.'));
  }

  /**
   * Submit handler for mark action.
   */
  public function submitMark(array &$form, FormStateInterface $form_state): void {
    $counts = $this->manager->markExisting(FALSE, FALSE);
    $this->messenger()->addStatus($this->formatCounts($counts, $this->t('Marked')));
    $form_state->setRebuild();
  }

  /**
   * Submit handler for wipe+mark action.
   */
  public function submitWipeMark(array &$form, FormStateInterface $form_state): void {
    $counts = $this->manager->markExisting(TRUE, FALSE);
    $this->messenger()->addStatus($this->formatCounts($counts, $this->t('Wiped & marked')));
    $form_state->setRebuild();
  }

  /**
   * Submit handler for immediate purge.
   */
  public function submitGoPurgeConfirm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('wdb_example.purge_confirm');
  }

  /**
   * Helper to format counts for messenger.
   */
  private function formatCounts(array $counts, $prefix) {
    $lines = [];
    foreach ($counts as $type => $n) {
      if ($n) {
        $lines[] = $type . '=' . $n;
      }
    }
    return $lines ? ($prefix . ': ' . implode(', ', $lines)) : $this->t('No entities processed.');
  }

}
