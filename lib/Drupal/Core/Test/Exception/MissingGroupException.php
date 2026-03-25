<?php

namespace Drupal\Core\Test\Exception;

/**
 * Exception thrown when a test class is missing the 'group' metadata.
 *
 * @see \Drupal\Core\Test\PhpUnitTestDiscovery::getTestClasses()
 */
class MissingGroupException extends \LogicException {
}
