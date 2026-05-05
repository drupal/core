<?php

declare(strict_types = 1);

namespace Drupal\ckeditor5\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * CKEditor 5 element.
 */
#[Constraint(
  id: 'CKEditor5Element',
  label: new TranslatableMarkup('CKEditor 5 element', [], ['context' => 'Validation'])
)]
class CKEditor5ElementConstraint extends SymfonyConstraint {

  /**
   * Constructs a CKEditor5ElementConstraint object.
   *
   * @param array|null $requiredAttributes
   *   Validation constraint option to impose attributes to be specified.
   * @param string $message
   *   Error message for invalid HTML.
   * @param string $missingRequiredAttributeMessage
   *   Error message if a tag is missing a required attribute.
   * @param string $requiredAttributeMinValuesMessage
   *   Error message if a tag does not have the minimum number of allowed values
   *   for an attribute.
   * @param array|null $groups
   *   The groups that the constraint belongs to.
   * @param mixed|null $payload
   *   Domain-specific data attached to a constraint.
   */
  public function __construct(
    public ?array $requiredAttributes = NULL,
    public $message = 'The following tag is not valid HTML: %provided_element.',
    public $missingRequiredAttributeMessage = 'The following tag is missing the required attribute <code>@required_attribute_name</code>: <code>@provided_element</code>.',
    public $requiredAttributeMinValuesMessage = 'The following tag does not have the minimum of @min_attribute_value_count allowed values for the required attribute <code>@required_attribute_name</code>: <code>@provided_element</code>.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
