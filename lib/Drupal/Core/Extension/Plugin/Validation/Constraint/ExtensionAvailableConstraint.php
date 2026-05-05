<?php

declare(strict_types = 1);

namespace Drupal\Core\Extension\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Check if an extension (module, theme, or profile) is available.
 */
#[Constraint(
  id: 'ExtensionAvailable',
  label: new TranslatableMarkup('Extension is available', [], ['context' => 'Validation'])
)]
class ExtensionAvailableConstraint extends SymfonyConstraint {

  /**
   * Constructs an ExtensionAvailableConstraint object.
   *
   * @param string $type
   *   The type of extension to look for. Can be 'module', 'theme' or 'profile'.
   * @param string $moduleNotExistsMessage
   *   The error message if the module does not exist.
   * @param string $themeNotExistsMessage
   *   The error message if the theme does not exist.
   * @param string $profileNotExistsMessage
   *   The error message if the profile does not exist.
   * @param string $couldNotLoadProfileToCheckExtension
   *   The error message if the profile could not be loaded.
   * @param array|null $groups
   *   The groups that the constraint belongs to.
   * @param mixed|null $payload
   *   Domain-specific data attached to a constraint.
   */
  public function __construct(
    public string $type,
    public string $moduleNotExistsMessage = "Module '@name' is not available.",
    public string $themeNotExistsMessage = "Theme '@name' is not available.",
    public string $profileNotExistsMessage = "Profile '@name' is not available.",
    public string $couldNotLoadProfileToCheckExtension = "Profile '@profile' could not be loaded to check if the extension '@extension' is available.",
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
