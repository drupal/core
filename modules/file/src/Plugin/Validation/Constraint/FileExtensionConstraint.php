<?php

declare(strict_types=1);

namespace Drupal\file\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * File extension constraint.
 */
#[Constraint(
  id: 'FileExtension',
  label: new TranslatableMarkup('File Extension', [], ['context' => 'Validation']),
  type: 'file'
)]
class FileExtensionConstraint extends SymfonyConstraint {

  public function __construct(
    public string $extensions,
    public string $message = 'Only files with the following extensions are allowed: %files-allowed.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
