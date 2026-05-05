<?php

declare(strict_types = 1);

namespace Drupal\ckeditor5\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Uniquely labeled list item constraint.
 *
 * @internal
 */
#[Constraint(
  id: 'UniqueLabelInList',
  label: new TranslatableMarkup('Unique label in list', [], ['context' => 'Validation'])
)]
class UniqueLabelInListConstraint extends SymfonyConstraint {

  /**
   * Constructs a UniqueLabelInListConstraint object.
   *
   * @param string $labelKey
   *   The key of the label that this validation constraint should check.
   * @param string $message
   *   The error message.
   * @param array|null $groups
   *   The groups that the constraint belongs to.
   * @param mixed|null $payload
   *   Domain-specific data attached to a constraint.
   */
  public function __construct(
    public string $labelKey,
    public $message = 'The label %label is not unique.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
