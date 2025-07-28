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
    // Note: While dependency injection is best practice, we are using the static
    // service container here to adhere to the existing code structure.
    $entity_type_manager = \Drupal::entityTypeManager();

    $form['vertical_tabs'] = ['#type' => 'vertical_tabs'];

    // --- Dynamically generate a settings tab for each subsystem ---
    $term_storage = $entity_type_manager->getStorage('taxonomy_term');
    $tids = $term_storage->getQuery()->condition('vid', 'subsystem')->sort('weight')->accessCheck(FALSE)->execute();
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
        '#description' => $this->t('Do not include slashes at the beginning or end.'),
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
    // Save settings for each subsystem.
    $subsystems_values = $form_state->getValue('subsystems');
    if (is_array($subsystems_values)) {
      foreach ($subsystems_values as $term_id => $values) {
        if (!is_numeric($term_id)) {
          continue;
        }

        $config_name = 'wdb_core.subsystem.' . $term_id;
        $this->config($config_name)
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
          ->set('export_templates.tei', $values['export_templates']['tei'])
          ->set('export_templates.rdf', $values['export_templates']['rdf'])
          ->save();
      }
    }

    parent::submitForm($form, $form_state);
  }

}
