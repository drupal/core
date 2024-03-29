<?php

namespace Drupal\options\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'list_key' formatter.
 */
#[FieldFormatter(
  id: 'list_key',
  label: new TranslatableMarkup('Key'),
  field_types: [
    'list_integer',
    'list_float',
    'list_string',
  ],
)]
class OptionsKeyFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#markup' => $item->value,
        '#allowed_tags' => FieldFilteredMarkup::allowedTags(),
      ];
    }

    return $elements;
  }

}
