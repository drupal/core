<?php

declare(strict_types = 1);

namespace Drupal\Core\Extension\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that the value is the name of an installed extension.
 */
#[Constraint(
  id: 'ExtensionExists',
  label: new TranslatableMarkup('Extension exists', [], ['context' => 'Validation'])
)]
class ExtensionExistsConstraint extends SymfonyConstraint {

  /**
   * Constructs an ExtensionExistsConstraint.
   *
   * @param string $type
   *   The type of extension to look for. Can be 'module' or 'theme'.
   * @param string $moduleMessage
   *   Error message if module is not installed.
   * @param string $themeMessage
   *   Error message if theme is not installed.
   * @param array|null $groups
   *   The groups that the constraint belongs to.
   * @param mixed|null $payload
   *   Domain-specific data attached to a constraint.
   */
  public function __construct(
    public string $type,
    public string $moduleMessage = "Module '@name' is not installed.",
    public string $themeMessage = "Theme '@name' is not installed.",
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
