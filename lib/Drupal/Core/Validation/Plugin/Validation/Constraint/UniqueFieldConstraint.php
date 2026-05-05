<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks if an entity field has a unique value.
 */
#[Constraint(
  id: 'UniqueField',
  label: new TranslatableMarkup('Unique field constraint', [], ['context' => 'Validation'])
)]
class UniqueFieldConstraint extends SymfonyConstraint {

  /**
   * Constructs a UniqueFieldConstraint object.
   *
   * @param bool $caseSensitive
   *   This constraint is case-insensitive by default. For example, "FOO" and
   *   "foo" would be considered as equivalent, and validation of the constraint
   *   would fail.
   * @param string $message
   *   The error message.
   * @param array|null $groups
   *   The groups that the constraint belongs to.
   * @param mixed|null $payload
   *   Domain-specific data attached to a constraint.
   */
  public function __construct(
    public bool $caseSensitive = FALSE,
    public $message = 'A @entity_type with @field_name %value already exists.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return '\Drupal\Core\Validation\Plugin\Validation\Constraint\UniqueFieldValueValidator';
  }

}
