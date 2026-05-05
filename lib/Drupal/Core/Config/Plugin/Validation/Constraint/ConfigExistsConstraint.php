<?php

declare(strict_types = 1);

namespace Drupal\Core\Config\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that the value is the name of an existing config object.
 */
#[Constraint(
  id: 'ConfigExists',
  label: new TranslatableMarkup('Config exists', [], ['context' => 'Validation'])
)]
class ConfigExistsConstraint extends SymfonyConstraint {

  /**
   * Constructs a ConfigExistsConstraint object.
   *
   * @param string $prefix
   *   Optional prefix, to be specified when this contains a config entity ID.
   *   Every config entity type can have multiple instances, all with unique IDs
   *   but the same config prefix. When config refers to a config entity,
   *   typically only the ID is stored, not the prefix.
   * @param string $message
   *   The error message if the config does not exist.
   * @param array|null $groups
   *   The groups that the constraint belongs to.
   * @param mixed|null $payload
   *   Domain-specific data attached to a constraint.
   */
  public function __construct(
    public string $prefix = '',
    public string $message = "The '@name' config does not exist.",
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
