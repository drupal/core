<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\StringTranslation;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the TranslatableMarkup class.
 *
 * @coversDefaultClass \Drupal\Core\StringTranslation\PluralTranslatableMarkup
 * @group StringTranslation
 */
class PluralTranslatableMarkupTest extends UnitTestCase {

  /**
   * Tests serialization of PluralTranslatableMarkup().
   *
   * @dataProvider providerPluralTranslatableMarkupSerialization
   */
  public function testPluralTranslatableMarkupSerialization($count, $expected_text): void {
    // Add a mock string translation service to the container.
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    // Create an object to serialize and unserialize.
    $markup = new PluralTranslatableMarkup($count, 'singular @count', 'plural @count');
    $serialized_markup = unserialize(serialize($markup));
    $this->assertEquals($expected_text, $serialized_markup->render());
  }

  /**
   * Data provider for ::testPluralTranslatableMarkupSerialization().
   */
  public static function providerPluralTranslatableMarkupSerialization() {
    return [
      [1, 'singular 1'],
      [2, 'plural 2'],
    ];
  }

  /**
   * Tests when the plural translation is missing.
   */
  public function testMissingPluralTranslation(): void {
    $markup = PluralTranslatableMarkup::createFromTranslatedString(2, 'There is no plural delimiter @count');
    $this->assertEquals('There is no plural delimiter 2', $markup->render());
  }

}
