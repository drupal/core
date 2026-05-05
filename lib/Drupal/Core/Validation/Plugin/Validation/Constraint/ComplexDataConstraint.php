<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Complex data constraint.
 *
 * Validates properties of complex data structures.
 */
#[Constraint(
  id: 'ComplexData',
  label: new TranslatableMarkup('Complex data', [], ['context' => 'Validation'])
)]
class ComplexDataConstraint extends SymfonyConstraint {

  /**
   * An array of constraints for contained properties, keyed by property name.
   *
   * @var array
   */
  public $properties;

  public function __construct(
    ?array $properties = NULL,
    ?array $groups = NULL,
    mixed $payload = NULL,
    ...$otherProperties,
  ) {
    if ($properties === NULL) {
      if (!empty($otherProperties)) {
        $properties = $otherProperties;
      }
      else {
        throw new \InvalidArgumentException('Properties must be passed to ComplexData constraint');
      }
    }
    parent::__construct(groups: $groups, payload: $payload);
    $this->properties = $properties;
    $constraint_manager = \Drupal::service('validation.constraint');

    // Instantiate constraint objects for array definitions.
    foreach ($this->properties as &$constraints) {
      foreach ($constraints as $id => $options) {
        if (!is_object($options)) {
          $constraints[$id] = $constraint_manager->create($id, $options);
        }
      }
    }
  }

}
