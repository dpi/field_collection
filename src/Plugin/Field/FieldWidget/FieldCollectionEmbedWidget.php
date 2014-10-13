<?php

/**
 * @file
 * Contains \Drupal\field_collection\Plugin\Field\FieldWidget\FieldCollectionEmbedWidget.
 */

namespace Drupal\field_collection\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field_collection\Entity\FieldCollectionItem;

/**
 * Plugin implementation of the 'field_collection_embed' widget.
 *
 * @FieldWidget(
 *   id = "field_collection_embed",
 *   label = @Translation("Embedded"),
 *   field_types = {
 *     "field_collection"
 *   },
 *   settings = {
 *   }
 * )
 */
class FieldCollectionEmbedWidget extends WidgetBase {
  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // TODO: Detect recursion
    $field_name = $this->fieldDefinition->getName();

    // Nest the field collection item entity form in a dedicated parent space,
    // by appending [field_name, delta] to the current parent space.
    // That way the form values of the field collection item are separated.
    //
    // TODO: Remove 'field_collections' from parents if possible.  Just using
    // array($field_name, $delta) seems to cause a lockup when the form is submitted.
    $parents = array_merge($element['#field_parents'], array($field_name, $delta));

    $element += array(
      '#element_validate' => array('field_collection_field_widget_embed_validate'),
      '#parents' => $parents,
      '#field_name' => $field_name,
    );

    if ($this->fieldDefinition->getFieldStorageDefinition()->cardinality == 1) {
      $element['#type'] = 'fieldset';
    }

    $field_state = static::getWidgetState($element['#field_parents'], $field_name, $form_state);

    /*
      if (!empty($field['settings']['hide_blank_items']) && $delta == $field_state['items_count'] && $delta > 0) {
        // Do not add a blank item. Also see
        // field_collection_field_attach_form() for correcting #max_delta.
        $recursion--;
        return FALSE;
      }
      elseif (!empty($field['settings']['hide_blank_items']) && $field_state['items_count'] == 0) {
        // We show one item, so also specify that as item count. So when the
        // add button is pressed the item count will be 2 and we show to items.
        $field_state['items_count'] = 1;
      }
    */

    // TODO: Add above functionality.
    if ($field_state['items_count'] == 0) {
      $field_state['items_count'] = 1;
    }

    if (isset($field_state['field_collection_item'][$delta])) {
      $field_collection_item = $field_state['field_collection_item'][$delta];
    }
    else {
      $field_collection_item = $items[$delta]->getFieldCollectionItem(TRUE);
      // Put our entity in the form state, so FAPI callbacks can access it.
      $field_state['field_collection_item'][$delta] = $field_collection_item;
    }

    static::setWidgetState($element['#field_parents'], $field_name, $form_state, $field_state);

    $display = entity_get_form_display('field_collection_item', $this->fieldDefinition->getName(), 'default');
    $display->buildForm($field_collection_item, $element, $form_state);

    /*
      TODO: Figure out if field_collection_field_widget_embed_delay_required_validation
      is still necessary and restore this functionality if it is.
      if (empty($element['#required'])) {
        $element['#after_build'][] = 'field_collection_field_widget_embed_delay_required_validation';
      }

      TODO: Remove button on unlimited cardinality field collection fields
      if ($field['cardinality'] == FIELD_CARDINALITY_UNLIMITED) {
        $element['remove_button'] = array(
          '#delta' => $delta,
          '#name' => implode('_', $parents) . '_remove_button',
          '#type' => 'submit',
          '#value' => t('Remove'),
          '#validate' => array(),
          '#submit' => array('field_collection_remove_submit'),
          '#limit_validation_errors' => array(),
          '#ajax' => array(
            'path' => 'field_collection/ajax',
            'effect' => 'fade',
          ),
          '#weight' => 1000,
        );
      }
    */

    return $element;
  }
}