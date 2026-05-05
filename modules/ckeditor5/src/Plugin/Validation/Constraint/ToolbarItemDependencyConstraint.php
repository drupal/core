<?php

declare(strict_types = 1);

namespace Drupal\ckeditor5\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * A CKEditor 5 toolbar item.
 *
 * @internal
 */
#[Constraint(
  id: 'CKEditor5ToolbarItemDependencyConstraint',
  label: new TranslatableMarkup('CKEditor 5 toolbar item dependency', [], ['context' => 'Validation'])
)]
class ToolbarItemDependencyConstraint extends SymfonyConstraint {

  /**
   * Constructs a ToolbarItemDependencyConstraint.
   *
   * @param string $toolbarItem
   *   The toolbar item that this validation constraint requires to be enabled.
   * @param string $message
   *   The error message.
   * @param array|null $groups
   *   The groups that the constraint belongs to.
   * @param mixed|null $payload
   *   Domain-specific data attached to a constraint.
   */
  public function __construct(
    public string $toolbarItem,
    public $message = 'Depends on %toolbar_item, which is not enabled.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
