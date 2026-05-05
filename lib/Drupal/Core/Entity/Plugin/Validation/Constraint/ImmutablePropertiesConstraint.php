<?php

declare(strict_types = 1);

namespace Drupal\Core\Entity\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks if config entity properties have been changed.
 */
#[Constraint(
  id: 'ImmutableProperties',
  label: new TranslatableMarkup('Properties are unchanged', [], ['context' => 'Validation']),
  type: ['entity']
)]
class ImmutablePropertiesConstraint extends SymfonyConstraint {

  public function __construct(
    public array $properties,
    public string $message = "The '@name' property cannot be changed.",
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
