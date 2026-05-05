<?php

namespace Drupal\Core\Entity\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks if a value is an entity that has a specific field.
 */
#[Constraint(
  id: 'EntityHasField',
  label: new TranslatableMarkup('Entity has field', [], ['context' => 'Validation']),
  type: ['entity']
)]
class EntityHasFieldConstraint extends SymfonyConstraint {

  public function __construct(
    public string $field_name,
    public $message = 'The entity must have the %field_name field.',
    public $notFieldableMessage = 'The entity does not support fields.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
