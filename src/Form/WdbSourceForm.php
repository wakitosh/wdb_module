<?php

namespace Drupal\wdb_core\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the WDB Source entity edit forms.
 *
 * This class provides the form for creating and editing WdbSource entities and
 * adds custom status messages upon saving.
 *
 * @ingroup wdb_core
 */
class WdbSourceForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    // You can make adjustments to the form here if needed, for example,
    // to change field weights or add custom validation. For now, the parent.
    // Method should render all defined base fields correctly.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    // Set a custom status message based on whether the entity is new or being
    // updated.
    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label WDB Source.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label WDB Source.', [
          '%label' => $entity->label(),
        ]));
    }

    // Redirect the user back to the collection page after saving.
    $form_state->setRedirect('entity.wdb_source.collection');
  }

}
