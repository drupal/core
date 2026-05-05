<?php

namespace Drupal\Core\Entity\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks if a value is a valid entity type.
 */
#[Constraint(
  id: 'EntityType',
  label: new TranslatableMarkup('Entity type', [], ['context' => 'Validation']),
  type: ['entity', 'entity_reference']
)]
class EntityTypeConstraint extends SymfonyConstraint {

  public function __construct(
    public string $type,
    public $message = 'The entity must be of type %type.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
