<?php

namespace Drupal\filter\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * Theme hooks for filter.
 */
class FilterThemeHooks {

  public function __construct(protected AccountInterface $currentUser) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() : array {
    return [
      'filter_tips' => [
        'variables' => [
          'tips' => NULL,
          'long' => FALSE,
        ],
      ],
      'text_format_wrapper' => [
        'variables' => [
          'children' => NULL,
          'description' => NULL,
          'attributes' => [],
        ],
        'initial preprocess' => static::class . ':preprocessTextFormatWrapper',
      ],
      'filter_guidelines' => [
        'variables' => [
          'format' => NULL,
        ],
        'initial preprocess' => static::class . ':preprocessFilterGuidelines',
      ],
      'filter_caption' => [
        'variables' => [
          'node' => NULL,
          'tag' => NULL,
          'caption' => NULL,
          'classes' => NULL,
        ],
      ],
    ];
  }

  /**
   * Prepares variables for text format guideline templates.
   *
   * Default template: filter-guidelines.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - format: An object representing a text format.
   */
  public function preprocessFilterGuidelines(array &$variables): void {
    $format = $variables['format'];
    $variables['tips'] = [
      '#theme' => 'filter_tips',
      '#tips' => $this->getFilterTips($format->id()),
    ];

    // Add format id for filter.js.
    $variables['attributes']['data-drupal-format-id'] = $format->id();
  }

  /**
   * Prepares variables for text format wrapper templates.
   *
   * Default template: text-format-wrapper.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - attributes: An associative array containing properties of the element.
   */
  public function preprocessTextFormatWrapper(array &$variables): void {
    $variables['aria_description'] = FALSE;
    // Add element class and id for screen readers.
    if (isset($variables['attributes']['aria-describedby'])) {
      $variables['aria_description'] = TRUE;
      $variables['attributes']['id'] = $variables['attributes']['aria-describedby'];
      // Remove aria-describedby attribute as it shouldn't be visible here.
      unset($variables['attributes']['aria-describedby']);
    }
  }

  /**
   * Retrieves the filter tips.
   *
   * @param string|null $formatId
   *   (optional) The ID of the text format for which to retrieve tips. If
   *   omitted, will return tips for all formats accessible to the current user.
   *
   * @return array
   *   An associative array of filtering tips, keyed by the filter name. Each
   *   filtering tip is an associative array with elements:
   *   - tip: Tip text.
   *   - id: Filter ID.
   */
  protected function getFilterTips(?string $formatId = NULL): array {
    $formats = filter_formats($this->currentUser);

    $tips = [];

    // If only listing one format, extract it from the $formats array.
    if ($formatId !== NULL) {
      $formats = [$formats[$formatId]];
    }

    foreach ($formats as $format) {
      foreach ($format->filters() as $name => $filter) {
        if ($filter->status) {
          $tip = $filter->tips();
          if (isset($tip)) {
            $tips[$format->label()][$name] = [
              'tip' => ['#markup' => $tip],
              'id' => $name,
            ];
          }
        }
      }
    }

    return $tips;
  }

}
