<?php

declare(strict_types=1);

namespace Drupal\entity_test\Entity;

use Drupal\Core\Entity\EntityTypeInterface;

// cspell:ignore basefield

/**
 * Defines an entity type with a multivalue base field.
 *
 * @ContentEntityType(
 *   id = "entity_test_multivalue_basefield",
 *   label = @Translation("Entity Test with a multivalue base field"),
 *   base_table = "entity_test_multivalue_basefield",
 *   data_table = "entity_test_multivalue_basefield_field_data",
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "langcode" = "langcode",
 *   }
 * )
 */
class EntityTestMultiValueBaseField extends EntityTest {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['name']->setCardinality(2);

    return $fields;
  }

}
