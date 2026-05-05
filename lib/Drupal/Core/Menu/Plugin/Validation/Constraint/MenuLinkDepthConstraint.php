<?php

declare(strict_types=1);

namespace Drupal\Core\Menu\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Drupal\Core\Validation\Plugin\Validation\Constraint\RangeConstraint;

/**
 * Validates the link depth of a menu tree.
 */
#[Constraint(
  id: 'MenuLinkDepth',
  label: new TranslatableMarkup('Menu link depth', options: ['context' => 'Validation']),
  type: ['integer'],
)]
class MenuLinkDepthConstraint extends RangeConstraint {

  /**
   * Constructs a MenuLinkDepthConstraint.
   *
   * @param string|int $baseLevel
   *   The initial level of menu items that are being exposed (zero-based).
   * @param string|null $notInRangeMessage
   *   Error message if minimum and maximum are both set and value is less than
   *   minimum or more than maximum.
   * @param string|null $minMessage
   *   Error message if value is less than minimum.
   * @param string|null $maxMessage
   *   Error message if value is more than maximum.
   * @param string|null $invalidMessage
   *   The message if min and max values are numeric but the given value is not.
   * @param int|float|non-empty-string|null $min
   *   The minimum value, either numeric or a datetime string representation.
   * @param non-empty-string|null $minPropertyPath
   *   Property path to the min value.
   * @param int|float|non-empty-string|null $max
   *   The maximum value, either numeric or a datetime string representation.
   * @param non-empty-string|null $maxPropertyPath
   *   Property path to the max value.
   * @param array|null $groups
   *   The groups that the constraint belongs to.
   * @param mixed|null $payload
   *   Domain-specific data attached to a constraint.
   */
  public function __construct(
    public readonly string|int $baseLevel = 0,
    ?string $notInRangeMessage = NULL,
    ?string $minMessage = NULL,
    ?string $maxMessage = NULL,
    ?string $invalidMessage = NULL,
    mixed $min = NULL,
    ?string $minPropertyPath = NULL,
    mixed $max = NULL,
    ?string $maxPropertyPath = NULL,
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(
      $notInRangeMessage,
      $minMessage,
      $maxMessage,
      $invalidMessage,
      NULL,
      $min,
      $minPropertyPath,
      $max,
      $maxPropertyPath,
      $groups,
      $payload,
    );
  }

}
