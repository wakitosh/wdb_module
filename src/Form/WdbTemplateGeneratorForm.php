<?php

namespace Drupal\wdb_core\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\wdb_core\Entity\WdbSource;
use Drupal\wdb_core\Service\WdbTemplateGeneratorService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Provides a form to generate TSV templates for data import.
 *
 * This form offers two methods for template generation: one based on an
 * uploaded morphological analysis file (e.g., from MeCab), and another based
 * on a sample of data from an existing WDB Source entity.
 */
class WdbTemplateGeneratorForm extends FormBase implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The WDB template generator service.
   *
   * @var \Drupal\wdb_core\Service\WdbTemplateGeneratorService
   */
  protected WdbTemplateGeneratorService $templateGeneratorService;

  /**
   * Constructs a new WdbTemplateGeneratorForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\wdb_core\Service\WdbTemplateGeneratorService $template_generator_service
   *   The WDB template generator service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, WdbTemplateGeneratorService $template_generator_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->templateGeneratorService = $template_generator_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('wdb_core.template_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wdb_core_template_generator_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['enctype'] = 'multipart/form-data';

    $form['from_mecab'] = [
      '#type' => 'details',
      '#title' => $this->t('Generate from Morphological Analysis Result'),
      '#open' => TRUE,
    ];
    $form['from_mecab']['source_identifier_mecab'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source Identifier'),
      '#required' => TRUE,
    ];

    $form['from_mecab']['mecab_file'] = [
      '#type' => 'file',
      '#title' => $this->t('Analysis Result File'),
      '#description' => $this->t('Upload a UTF-8 encoded text file. The format should be compatible with the "Chaki import format" from WebChaMaMe.'),
      '#required' => TRUE,
    ];

    $form['from_mecab']['submit_mecab'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Template from Analysis'),
      '#submit' => ['::submitFormFromMecab'],
      '#validate' => ['::validateFormFromMecab'],
    ];

    $form['from_existing'] = [
      '#type' => 'details',
      '#title' => $this->t('Generate from Existing Source Data'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $source_storage = $this->entityTypeManager->getStorage('wdb_source');
    $sources = $source_storage->loadMultiple();
    $source_options = [];
    foreach ($sources as $source) {
      $source_options[$source->id()] = $source->label();
    }

    if (empty($source_options)) {
      $form['from_existing']['message'] = ['#markup' => '<p>' . $this->t('No source data has been registered yet.') . '</p>'];
    }
    else {
      $form['from_existing']['source_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Select Source Document'),
        '#options' => $source_options,
      ];
      $form['from_existing']['submit_existing'] = [
        '#type' => 'submit',
        '#value' => $this->t('Generate Template from Source'),
        '#submit' => ['::submitFormFromSource'],
        '#limit_validation_errors' => [
          ['from_existing', 'source_id'],
        ],
      ];
    }
    return $form;
  }

  /**
   * Validates the MeCab file upload.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateFormFromMecab(array &$form, FormStateInterface $form_state) {
    $all_files = $this->getRequest()->files->get('files', []);
    if (empty($all_files['mecab_file'])) {
      $form_state->setErrorByName('mecab_file', $this->t('File is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This is the default submit handler. Specific actions are handled by
    // submitFormFromMecab() and submitFormFromSource().
  }

  /**
   * Submit handler for generating a template from a MeCab file.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitFormFromMecab(array &$form, FormStateInterface $form_state) {
    $all_files = $this->getRequest()->files->get('files', []);
    $file = $all_files['mecab_file'];
    $source_identifier = $form_state->getValue('source_identifier_mecab');

    if ($file && $file->isValid()) {
      $file_path = $file->getRealPath();
      $context = [];
      $tsv_content = $this->templateGeneratorService->generateTemplateFromMecab($file_path, $source_identifier, $context);

      // After generation, check the context for any warnings.
      if (!empty($context['warnings'])) {
        $this->messenger()->addWarning($this->t('The template was generated, but @count POS string(s) could not be mapped to a lexical category. Please check the recent log messages for details.', ['@count' => count($context['warnings'])]));
      }
      else {
        $this->messenger()->addStatus($this->t('Template generated successfully.'));
      }

      $form_state->setResponse($this->downloadTsvResponse($tsv_content, 'template_mecab.tsv'));
    }
  }

  /**
   * Submit handler for generating a template from existing source data.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitFormFromSource(array &$form, FormStateInterface $form_state) {
    $from_existing_values = $form_state->getValue('from_existing');
    $source_id = $from_existing_values['source_id'] ?? NULL;

    if ($source_id) {
      $source = $this->entityTypeManager->getStorage('wdb_source')->load($source_id);
      if ($source instanceof WdbSource) {
        $tsv_content = $this->templateGeneratorService->generateTemplateFromSource($source);
        $filename = 'template_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $source->get('source_identifier')->value) . '.tsv';

        $form_state->setResponse($this->downloadTsvResponse($tsv_content, $filename));
      }
      else {
        $this->messenger()->addError($this->t('Failed to load the selected source.'));
      }
    }
  }

  /**
   * Creates a streamed response to download TSV content.
   *
   * @param string $content
   *   The TSV content to be downloaded.
   * @param string $filename
   *   The desired filename for the download.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   A response object that streams the file to the browser.
   */
  private function downloadTsvResponse(string $content, string $filename) {
    $response = new StreamedResponse(function () use ($content) {
      echo $content;
    });
    $response->headers->set('Content-Type', 'text/tab-separated-values; charset=utf-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

    return $response;
  }

}
