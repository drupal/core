<?php

declare(strict_types = 1);

namespace Drupal\Core\Config\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that config dependencies contain specific types of entities.
 */
#[Constraint(
  id: 'RequiredConfigDependencies',
  label: new TranslatableMarkup('Required config dependency types', [], ['context' => 'Validation'])
)]
class RequiredConfigDependenciesConstraint extends SymfonyConstraint {

  /**
   * Constructs a RequiredConfigDependenciesConstraint object.
   *
   * @param array $entityTypes
   *   The IDs of entity types that need to exist in config dependencies. For
   *   example, if an entity requires a filter format in its config
   *   dependencies, this should contain `filter_format`.
   * @param string $message
   *   The error message.
   * @param array|null $groups
   *   The groups that the constraint belongs to.
   * @param mixed|null $payload
   *   Domain-specific data attached to a constraint.
   */
  public function __construct(
    public array $entityTypes = [],
    public string $message = 'This @entity_type requires a @dependency_type.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
