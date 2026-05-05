<?php

namespace Drupal\user\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks if the user's email address is provided if required.
 *
 * The user mail field is NOT required if account originally had no mail set
 * and the user performing the edit has 'administer users' permission.
 * This allows users without email address to be edited and deleted.
 *
 * @property string $message
 *   Violation message. Use the same message as FormValidator. Note that the
 *   name argument is not sanitized so that translators only have one string to
 *   translate. The name is sanitized in self::validate().
 */
#[Constraint(
  id: 'UserMailRequired',
  label: new TranslatableMarkup('User email required', [], ['context' => 'Validation'])
)]
class UserMailRequired extends SymfonyConstraint {

  public function __construct(
    public string $message = '@name field is required.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
