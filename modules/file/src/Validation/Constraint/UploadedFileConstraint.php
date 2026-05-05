<?php

declare(strict_types=1);

namespace Drupal\file\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * A constraint for UploadedFile objects.
 */
class UploadedFileConstraint extends Constraint {

  /**
   * Constructs an UploadedFileConstraint object.
   *
   * @param int|null $maxSize
   *   The upload max size. Defaults to checking the environment.
   * @param string $uploadIniSizeErrorMessage
   *   Error message if file exceeds maximum allowed uploaded size set by the
   *   PHP environment.
   * @param string $uploadFormSizeErrorMessage
   *   Error message if file exceeds maximum allowed uploaded size set for the
   *   form.
   * @param string $uploadPartialErrorMessage
   *   Error message if the upload only partially completed.
   * @param string $uploadNoFileErrorMessage
   *   Error message if the uploaded file could not be found.
   * @param string $uploadErrorMessage
   *   Generic error message for upload failure.
   * @param array|null $groups
   *   An array of validation groups.
   * @param mixed|null $payload
   *   Domain-specific data attached to a constraint.
   */
  public function __construct(
    public ?int $maxSize = NULL,
    public string $uploadIniSizeErrorMessage = 'The file %file could not be saved because it exceeds %maxsize, the maximum allowed size for uploads.',
    public string $uploadFormSizeErrorMessage = 'The file %file could not be saved because it exceeds %maxsize, the maximum allowed size for uploads.',
    public string $uploadPartialErrorMessage = 'The file %file could not be saved because the upload did not complete.',
    public string $uploadNoFileErrorMessage = 'The file %file could not be saved because the upload did not complete.',
    public string $uploadErrorMessage = 'The file %file could not be saved. An unknown error has occurred.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
