<?php

namespace Drupal\wdb_core\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the WDB Annotation Page entity edit forms.
 *
 * This class provides the form for creating and editing WdbAnnotationPage
 * entities and adds custom status messages upon saving.
 *
 * @ingroup wdb_core
 */
class WdbAnnotationPageForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    // Set a custom status message based on whether the entity is new or being updated.
    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label Annotation Page.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label Annotation Page.', [
          '%label' => $entity->label(),
        ]));
    }

    // Redirect the user back to the collection page after saving.
    $form_state->setRedirect('entity.wdb_annotation_page.collection');

    return $status;
  }

}
