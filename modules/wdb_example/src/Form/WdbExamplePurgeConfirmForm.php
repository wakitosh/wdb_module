<?php

namespace Drupal\wdb_example\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for purging tracked example data.
 */
class WdbExamplePurgeConfirmForm extends ConfirmFormBase {

  /**
   * Example manager service.
   *
   * @var object
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->manager = $container->get('wdb_example.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wdb_example_purge_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to purge all tracked example data?');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Purge');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('wdb_example.admin_form');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    // Show a brief summary of what will be purged.
    $counts = $this->manager->getMapCounts();
    $summary = $this->t('Tracked entities: @total', ['@total' => $counts['total'] ?? 0]);
    if (!empty($counts['by_type'])) {
      $pieces = [];
      foreach ($counts['by_type'] as $type => $n) {
        $pieces[] = $type . '=' . $n;
      }
      $summary = $summary . ' (' . implode(', ', $pieces) . ')';
    }
    $form['description'] = [
      '#markup' => '<p>' . $this->t('This action cannot be undone.') . '</p><p>' . $summary . '</p>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) : void {
    $summary = $this->manager->purgeTracked(FALSE);
    $this->manager->logPurgeSummary($summary);
    $deleted = $summary['total_deleted'] ?? 0;
    $this->messenger()->addStatus($this->t('Purged @n entities. See recent logs for details.', ['@n' => $deleted]));
    $form_state->setRedirect('wdb_example.admin_form');
  }

}
