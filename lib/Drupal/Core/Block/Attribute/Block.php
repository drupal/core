<?php

namespace Drupal\Core\Block\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The Block attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Block extends Plugin {

  /**
   * Constructs a Block attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $admin_label
   *   The administrative label of the block.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $category
   *   (optional) The category in the admin UI where the block will be listed.
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface[] $context_definitions
   *   (optional) An array of context definitions describing the context used by
   *   the plugin. The array is keyed by context names.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   * @param array<string, string|false> $forms
   *   (optional) An array of form class names or FALSE, keyed by a string.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $admin_label = NULL,
    public readonly ?TranslatableMarkup $category = NULL,
    public readonly array $context_definitions = [],
    public readonly ?string $deriver = NULL,
    public readonly array $forms = [],
  ) {}

}
