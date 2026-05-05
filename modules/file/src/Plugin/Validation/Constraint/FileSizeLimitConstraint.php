<?php

namespace Drupal\file\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * File size max constraint.
 */
#[Constraint(
  id: 'FileSizeLimit',
  label: new TranslatableMarkup('File Size Limit', [], ['context' => 'Validation']),
  type: 'file'
)]
class FileSizeLimitConstraint extends SymfonyConstraint {

  public function __construct(
    public int $fileLimit = 0,
    public int $userLimit = 0,
    public string $maxFileSizeMessage = 'The file is %filesize exceeding the maximum file size of %maxsize.',
    public string $diskQuotaMessage = 'The file is %filesize which would exceed your disk quota of %quota.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
