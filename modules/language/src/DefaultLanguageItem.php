<?php

namespace Drupal\language;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\LanguageItem;

/**
 * Alternative plugin implementation of the 'language' field type.
 *
 * Replaces the Core 'language' entity field type implementation, changes the
 * default values used.
 *
 * Required settings are:
 *  - target_type: The entity type to reference.
 *
 * @see \Drupal\language\Hook\LanguageHooks::fieldInfoAlter().
 */
class DefaultLanguageItem extends LanguageItem {

  /**
   * {@inheritdoc}
   */
  public function applyDefaultValue($notify = TRUE) {
    $langcode = $this->getDefaultLangcode($this->getEntity());
    // Always notify otherwise default langcode will not be set correctly.
    $this->setValue(['value' => $langcode], TRUE);
    return $this;
  }

  /**
   * Provides default language code of given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity whose language code to be loaded.
   *
   * @return string
   *   A string language code.
   */
  public function getDefaultLangcode(EntityInterface $entity) {
    return language_get_default_langcode($entity->getEntityTypeId(), $entity->bundle());
  }

}
