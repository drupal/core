<?php

declare(strict_types=1);

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks if a bundle exists on a certain content entity type.
 *
 * This differs from the `Bundle` constraint in that checks that the validated
 * value is the *name of a bundle* of a particular entity type.
 */
#[Constraint(
  id: 'EntityBundleExists',
  label: new TranslatableMarkup('Entity bundle exists', [], ['context' => 'Validation']),
  type: 'entity'
)]
class EntityBundleExistsConstraint extends SymfonyConstraint {

  /**
   * Constructs an EntityBundleExistsConstraint.
   *
   * @param string $entityTypeId
   *   The entity type ID which should have the given bundle. This can contain
   *   variable values (e.g., `%parent`) that will be replaced.
   *   @see \Drupal\Core\Config\Schema\TypeResolver::replaceVariable()
   * @param string $message
   *   The error message.
   * @param array|null $groups
   *   The groups that the constraint belongs to.
   * @param mixed|null $payload
   *   Domain-specific data attached to a constraint.
   */
  public function __construct(
    public string $entityTypeId,
    public $message = "The '@bundle' bundle does not exist on the '@entity_type_id' entity type.",
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
