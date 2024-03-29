<?php

namespace Drupal\views\Plugin\views\argument;

use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\BrokenHandlerTrait;

/**
 * A special handler to take the place of missing or broken handlers.
 *
 * @ingroup views_argument_handlers
 */
#[ViewsArgument(
  id: 'broken',
)]
class Broken extends ArgumentPluginBase {
  use BrokenHandlerTrait;

}
