<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Form;

use Drupal\Core\Form\FormSubmitter;

/**
 * Provides a stub FormSubmitter that doesn't depend on batch_get().
 */
class StubFormSubmitter extends FormSubmitter {

  protected function &batchGet(): array {
    $batch = [];
    return $batch;
  }

}
