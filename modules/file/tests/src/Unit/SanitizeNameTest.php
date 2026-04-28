<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Unit;

use Drupal\Component\Transliteration\PhpTransliteration;
use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\file\EventSubscriber\FileEventSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

// cSpell:ignore TÉXT äöüåøhello aouaohello aeoeueaohello Pácê
/**
 * Filename sanitization tests.
 */
#[Group('file')]
class SanitizeNameTest extends UnitTestCase {

  /**
   * Test file name sanitization.
   *
   * @param string $original
   *   The original filename.
   * @param string $expected
   *   The expected filename.
   * @param array $options
   *   Array of filename sanitization options, in this order:
   *   0: boolean Transliterate.
   *   1: string Replace whitespace.
   *   2: string Replace non-alphanumeric characters.
   *   3: boolean De-duplicate separators.
   *   4: boolean Convert to lowercase.
   * @param string $language_id
   *   Optional language code for transliteration. Defaults to 'en'.
   *
   * @legacy-covers \Drupal\file\EventSubscriber\FileEventSubscriber::sanitizeFilename
   * @legacy-covers \Drupal\Core\File\Event\FileUploadSanitizeNameEvent::__construct
   */
  #[DataProvider('provideFilenames')]
  public function testFileNameTransliteration(string $original, string $expected, array $options, string $language_id = 'en'): void {
    $sanitization_options = [
      'transliterate' => $options[0],
      'replacement_character' => $options[1],
      'replace_whitespace' => $options[2],
      'replace_non_alphanumeric' => $options[3],
      'deduplicate_separators' => $options[4],
      'lowercase' => $options[5],
    ];
    $config_factory = $this->getConfigFactoryStub([
      'file.settings' => [
        'filename_sanitization' => $sanitization_options,
      ],
    ]);

    $language = new Language(['id' => $language_id]);
    $language_manager = $this->prophesize(LanguageManagerInterface::class);
    $language_manager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->willReturn($language);

    $event = new FileUploadSanitizeNameEvent($original, $language_id);
    $subscriber = new FileEventSubscriber($config_factory, new PhpTransliteration(), $language_manager->reveal());
    $subscriber->sanitizeFilename($event);

    // Check the results of the configured sanitization.
    $this->assertEquals($expected, $event->getFilename());
  }

  /**
   * Provides data for testFileNameTransliteration().
   *
   * @return array
   *   Arrays with original name, expected name, and sanitization options.
   */
  public static function provideFilenames(): array {
    return [
      'Test default options' => [
        'TÉXT-œ.txt',
        'TÉXT-œ.txt',
        [FALSE, '-', FALSE, FALSE, FALSE, FALSE],
      ],
      'Test raw file without extension' => [
        'TÉXT-œ',
        'TÉXT-œ',
        [FALSE, '-', FALSE, FALSE, FALSE, FALSE],
      ],
      'Test only transliteration: simple' => [
        'Á-TÉXT-œ.txt',
        'A-TEXT-oe.txt',
        [TRUE, '-', FALSE, FALSE, FALSE, FALSE],
      ],
      'Test only transliteration: raw file without extension' => [
        'Á-TÉXT-œ',
        'A-TEXT-oe',
        [TRUE, '-', FALSE, FALSE, FALSE, FALSE],
      ],
      'Test only transliteration: complex and replace (-)' => [
        'S  Pácê--táb#	#--🙈.jpg',
        'S  Pace--tab#	#---.jpg',
        [TRUE, '-', FALSE, FALSE, FALSE, FALSE],
      ],
      'Test only transliteration: complex and replace (_)' => [
        'S  Pácê--táb#	#--🙈.jpg',
        'S  Pace--tab#	#--_.jpg',
        [TRUE, '_', FALSE, FALSE, FALSE, FALSE],
      ],
      'Test transliteration, replace (-) and replace whitespace (trim front)' => [
        '  S  Pácê--táb#	#--🙈.png',
        'S--Pace--tab#-#---.png',
        [TRUE, '-', TRUE, FALSE, FALSE, FALSE],
      ],
      'Test transliteration, replace (-) and replace whitespace (trim both sides)' => [
        '  S  Pácê--táb#	#--🙈   .jpg',
        'S--Pace--tab#-#---.jpg',
        [TRUE, '-', TRUE, FALSE, FALSE, FALSE],
      ],
      'Test transliteration, replace (_) and replace whitespace (trim both sides)' => [
        '  S  Pácê--táb#	#--🙈  .jpg',
        'S__Pace--tab#_#--_.jpg',
        [TRUE, '_', TRUE, FALSE, FALSE, FALSE],
      ],
      'Test transliteration, replace (_), replace whitespace and replace non-alphanumeric' => [
        '  S  Pácê--táb#	#--🙈.txt',
        'S__Pace--tab___--_.txt',
        [TRUE, '_', TRUE, TRUE, FALSE, FALSE],
      ],
      'Test transliteration, replace (-), replace whitespace and replace non-alphanumeric' => [
        '  S  Pácê--táb#	#--🙈.txt',
        'S--Pace--tab------.txt',
        [TRUE, '-', TRUE, TRUE, FALSE, FALSE],
      ],
      'Test transliteration, replace (-), replace whitespace, replace non-alphanumeric and removing duplicate separators' => [
        'S  Pácê--táb#	#--🙈.txt',
        'S-Pace-tab.txt',
        [TRUE, '-', TRUE, TRUE, TRUE, FALSE],
      ],
      'Test transliteration, replace (-), replace whitespace and deduplicate separators' => [
        '  S  Pácê--táb#	#--🙈.txt',
        'S-Pace-tab#-#.txt',
        [TRUE, '-', TRUE, FALSE, TRUE, FALSE],
      ],
      'Test transliteration, replace (_), replace whitespace, replace non-alphanumeric and deduplicate separators' => [
        'S  Pácê--táb#	#--🙈.txt',
        'S_Pace_tab.txt',
        [TRUE, '_', TRUE, TRUE, TRUE, FALSE],
      ],
      'Test transliteration, replace (-), replace whitespace, replace non-alphanumeric, deduplicate separators and lowercase conversion' => [
        'S  Pácê--táb#	#--🙈.jpg',
        's-pace-tab.jpg',
        [TRUE, '-', TRUE, TRUE, TRUE, TRUE],
      ],
      'Test transliteration, replace (_), replace whitespace, replace non-alphanumeric, deduplicate separators and lowercase conversion' => [
        'S  Pácê--táb#	#--🙈.txt',
        's_pace_tab.txt',
        [TRUE, '_', TRUE, TRUE, TRUE, TRUE],
      ],
      'Ignore non-alphanumeric replacement if transliteration is not set, but still replace whitespace, deduplicate separators, and lowercase' => [
        '  2S  Pácê--táb#	#--🙈.txt',
        '2s-pácê-táb#-#-🙈.txt',
        [FALSE, '-', TRUE, TRUE, TRUE, TRUE],
      ],
      'Only lowercase, simple' => [
        'TEXT.txt',
        'text.txt',
        [TRUE, '-', FALSE, FALSE, FALSE, TRUE],
      ],
      'Only lowercase, with unicode' => [
        'TÉXT.txt',
        'text.txt',
        [TRUE, '-', FALSE, FALSE, FALSE, TRUE],
      ],
      'No transformations' => [
        'Ä Ö Ü Å Ø äöüåøhello.txt',
        'Ä Ö Ü Å Ø äöüåøhello.txt',
        [FALSE, '-', FALSE, FALSE, FALSE, FALSE],
      ],
      'Transliterate via en (not de), no other transformations' => [
        'Ä Ö Ü Å Ø äöüåøhello.txt',
        'A O U A O aouaohello.txt',
        [TRUE, '-', FALSE, FALSE, FALSE, FALSE],
      ],
      'Transliterate via de (not en), no other transformations' => [
        'Ä Ö Ü Å Ø äöüåøhello.txt',
        'Ae Oe Ue A O aeoeueaohello.txt',
        [TRUE, '-', FALSE, FALSE, FALSE, FALSE], 'de',
      ],
      'Transliterate via de not en, plus whitespace + lowercase' => [
        'Ä Ö Ü Å Ø äöüåøhello.txt',
        'ae-oe-ue-a-o-aeoeueaohello.txt',
        [TRUE, '-', TRUE, FALSE, FALSE, TRUE], 'de',
      ],
      'Remove duplicate separators with falsey extension' => [
        'foo.....0',
        'foo.0',
        [TRUE, '-', FALSE, FALSE, TRUE, FALSE],
      ],
      'Remove duplicate separators with extension and ending in dot' => [
        'foo.....txt',
        'foo.txt',
        [TRUE, '-', FALSE, FALSE, TRUE, FALSE],
      ],
      'Remove duplicate separators without extension and ending in dot' => [
        'foo.....',
        'foo',
        [TRUE, '-', FALSE, FALSE, TRUE, FALSE],
      ],
      'All unknown unicode' => [
        '🙈🙈🙈.txt',
        '---.txt',
        [TRUE, '-', FALSE, FALSE, FALSE, FALSE],
      ],
      '✓ unicode' => [
        '✓.txt',
        '-.txt',
        [TRUE, '-', FALSE, FALSE, FALSE, FALSE],
      ],
      'Multiple ✓ unicode' => [
        '✓✓✓.txt',
        '---.txt',
        [TRUE, '-', FALSE, FALSE, FALSE, FALSE],
      ],
      'Test transliteration, replace (-), replace whitespace and removing multiple duplicate separators #1' => [
        'Test_-_file.png',
        'test-file.png',
        [TRUE, '-', TRUE, TRUE, TRUE, TRUE],
      ],
      'Test transliteration, replace (-), replace whitespace and removing multiple duplicate separators #2' => [
        'Test .. File.png',
        'test-file.png',
        [TRUE, '-', TRUE, TRUE, TRUE, TRUE],
      ],
      'Test transliteration, replace (-), replace whitespace and removing multiple duplicate separators #3' => [
        'Test -..__-- file.png',
        'test-file.png',
        [TRUE, '-', TRUE, TRUE, TRUE, TRUE],
      ],
      'Test transliteration, replace (-), replace sequences of dots, underscores and/or dashes with the replacement character' => [
        'abc. --_._-- .abc.jpeg',
        'abc. - .abc.jpeg',
        [TRUE, '-', FALSE, FALSE, TRUE, FALSE],
      ],
    ];
  }

}
