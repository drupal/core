<?php

declare(strict_types=1);

namespace Drupal\Core\Extension;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The Theme extension object.
 *
 * Extending this class is not supported and no BC is provided for subclasses.
 *
 * @see \Drupal\Core\Extension\ThemeExtensionList::doList()
 *
 * @todo https://www.drupal.org/project/drupal/issues/3026232 Replace public
 *   and dynamic properties with methods.
 *
 * @final
 *
 * @property string $base_theme
 *   Immediate parent base theme machine name.
 * @property array<string, string|null> $base_themes
 *   All base theme ancestors' names keyed by machine name.
 *   A NULL entry marks a missing base theme or a cycle.
 * @property string $engine
 *   Theme engine machine name.
 * @property bool $incompatible_base
 *   Whether a base theme is missing or the base theme chain contains a cycle.
 * @property bool $incompatible_engine
 *   Whether the specified theme engine could not be found.
 * @property bool $incompatible_module
 *   Whether a required module is missing, is incompatible with Drupal core,
 *   or fails the required version constraint.
 * @property bool $incompatible_php
 *   Whether the theme requires a newer PHP version.
 * @property bool $incompatible_region
 *   Whether the theme lacks the required 'content' region.
 * @property bool $insufficient_module_permissions
 *   Whether a compatible module dependency is disabled and the current user
 *   lacks the 'administer modules' permission.
 * @property bool $is_admin
 *   Whether this theme is used for administration pages.
 * @property bool $is_default
 *   Whether this is the site's default theme.
 * @property array $libraries
 *   Libraries listed in the theme info file.
 * @property array<string, \Drupal\Core\Extension\Dependency> $module_dependencies
 *   Module dependencies keyed by machine name.
 * @property array<string, string> $module_dependencies_disabled
 *   Disabled, compatible module dependencies' names keyed by machine name.
 * @property array<string, \Drupal\Core\StringTranslation\TranslatableMarkup> $module_dependencies_list
 *   Module dependency labels with status messages, keyed by machine name.
 * @property list<\Drupal\Component\Render\MarkupInterface> $notes
 *   Notes on the theme's role and lifecycle.
 * @property list<array<string, mixed>> $operations
 *   Available operation links.
 * @property string $owner
 *   Root base theme's engine file path.
 * @property string $prefix
 *   Root base theme's engine machine name.
 * @property array<string, mixed>|null $screenshot
 *   Screenshot array, or NULL if no screenshot is found.
 * @property array<string, string> $sub_themes
 *   Direct and indirect sub-themes' names keyed by machine name.
 */
class Theme extends Extension {

  /**
   * Constructs a new Theme object.
   *
   * @param string $root
   *   The app root.
   * @param string $pathname
   *   The relative path and filename of the extension's info file; e.g.,
   *   'core/themes/my_theme/my_theme.info.yml'.
   * @param array $info
   *   The info array parsed from the theme's .info.yml file.
   * @param string|null $filename
   *   (optional) The filename of the main extension file; e.g., my_theme.theme.
   *   This is deprecated and will be removed in Drupal 13.0.0.
   */
  public function __construct(string $root, string $pathname, array $info, ?string $filename = NULL) {
    parent::__construct($root, 'theme', $pathname, $filename);
    $this->info = $info;
  }

  /**
   * Lists all the theme's regions.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   An array of human-readable region names keyed by machine names.
   */
  public function listAllRegions(): array {
    return array_map(static function ($label) {
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      return new TranslatableMarkup($label);
    }, $this->info['regions']);
  }

  /**
   * Lists all the theme's visible regions.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   An array of human-readable region names keyed by machine names.
   */
  public function listVisibleRegions(): array {
    // List only regions that do not appear in the 'regions_hidden' key.
    return array_diff_key(
      $this->listAllRegions(),
      array_flip($this->info['regions_hidden'])
    );
  }

  /**
   * Gets the name of the default region for the theme.
   *
   * @return string
   *   The machine name of the default region.
   */
  public function getDefaultRegion(): string {
    return (string) key($this->listVisibleRegions());
  }

}
