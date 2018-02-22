<?php

namespace Drupal\Tests\Core\Common;

use Drupal\Component\Utility\Tags;
use Drupal\Tests\UnitTestCase;

/**
 * Tests explosion and implosion of autocomplete tags.
 *
 * @group Common
 */
class TagsTest extends UnitTestCase {

  protected $validTags = [
    'Drupal' => 'Drupal',
    'Drupal with some spaces' => 'Drupal with some spaces',
    '"Legendary Drupal mascot of doom: ""Druplicon"""' => 'Legendary Drupal mascot of doom: "Druplicon"',
    '"Drupal, although it rhymes with sloopal, is as awesome as a troopal!"' => 'Drupal, although it rhymes with sloopal, is as awesome as a troopal!',
  ];

  /**
   * Explodes a series of tags.
   */
  public function explodeTags() {
    $string = implode(', ', array_keys($this->validTags));
    $tags = Tags::explode($string);
    $this->assertTags($tags);
  }

  /**
   * Implodes a series of tags.
   */
  public function testImplodeTags() {
    $tags = array_values($this->validTags);
    // Let's explode and implode to our heart's content.
    for ($i = 0; $i < 10; $i++) {
      $string = Tags::implode($tags);
      $tags = Tags::explode($string);
    }
    $this->assertTags($tags);
  }

  /**
   * Helper function: asserts that the ending array of tags is what we wanted.
   */
  protected function assertTags($tags) {
    $original = $this->validTags;
    foreach ($tags as $tag) {
      $key = array_search($tag, $original);
      $this->assertTrue((bool) $key, $tag, sprintf('Make sure tag %s shows up in the final tags array (originally %s)', $tag, $key));
      unset($original[$key]);
    }
    foreach ($original as $leftover) {
      $this->fail(sprintf('Leftover tag %s was left over.', $leftover));
    }
  }

  /**
   * Test converting a string to tags.
   *
   * @param string $string
   *   String to explode.
   * @param array $tagsExpected
   *   Expected result after explosion, or best effort if errors found.
   * @param bool $hasError
   *   Whether errors are logged.
   *
   * @dataProvider providerTestExplode
   */
  public function testExplode($string, $tagsExpected, $hasError) {
    $tags = Tags::explode($string, $errors);
    $this->assertEquals($tagsExpected, $tags);
    $this->assertEquals($hasError, count($errors) > 0);
  }

  /**
   * Provides test data for testExplode().
   * @return array
   */
  public function providerTestExplode() {
    $tests = [];

    $tests['unquoted'] = [
      'hello',
      ['hello'],
      FALSE,
    ];
    $tests['unquoted multiword'] = [
      'Drupal with some spaces',
      ['Drupal with some spaces'],
      FALSE,
    ];
    $tests['quoted, tag with comma'] = [
      '"Hello, World"',
      ['Hello, World'],
      FALSE,
    ];
    $tests['quoted, missing trailing quote'] = [
      '"Hello',
      [],
      TRUE,
    ];
    $tests['unquoted, unexpected quote'] = [
      'Hello"',
      [],
      TRUE,
    ];
    $tests['unquoted, empty tags'] = [
      ',,,,,,',
      [],
      FALSE,
    ];
    $tests['unescaped, empty tags, word, empty tags'] = [
      ',,hello,,',
      ['hello'],
      FALSE,
    ];
    $tests['quoted, empty'] = [
      '"hello",',
      ['hello'],
      FALSE,
    ];
    $tests['unquoted, tag, empty tag'] = [
      'hello,',
      ['hello'],
      FALSE,
    ];
    $tests['unquoted, quoted'] = [
      'unquoted,"quoted2"',
      ['unquoted', 'quoted2'],
      FALSE,
    ];
    $tests['unquoted, unquoted'] = [
      'unquoted,unquoted2',
      ['unquoted', 'unquoted2'],
      FALSE,
    ];
    $tests['quoted, unquoted'] = [
      '"quoted",unquoted',
      ['quoted', 'unquoted'],
      FALSE,
    ];
    $tests['quoted, quoted'] = [
      '"quoted","quoted2"',
      ["quoted", "quoted2"],
      FALSE,
    ];
    $tests['empty tag, unquoted'] = [
      ',hello',
      ['hello'],
      FALSE,
    ];
    $tests['empty tag, quoted'] = [
      ',"hello"',
      ['hello'],
      FALSE,
    ];
    $tests['quoted, unexpected quote'] = [
      '"Hello "Foo bar" World, baz"',
      ['Hello '],
      TRUE,
    ];
    $tests['quoted, missing comma, unquoted'] = [
      '"Hello" World',
      ['Hello'],
      TRUE,
    ];
    $tests['quoted, missing comma, quoted'] = [
      '"Hello" "World"',
      ['Hello'],
      TRUE,
    ];
    $tests['unquoted, unexpected quote, word'] = [
      'Hello "Foo bar" World, baz',
      [],
      TRUE,
    ];
    $tests['Quoted with no contents, missing comma'] = [
      '""Hello "Foo bar" World, baz"',
      [],
      TRUE,
    ];
    $tests['unquoted, unquoted, trailing escaped'] = [
      'Hello Foo bar World, baz""',
      ['Hello Foo bar World', 'baz"'],
      FALSE,
    ];
    $tests['unquoted, word within escaped, quoted, empty tag, unexpected character'] = [
      'Hello ""Foo bar"" World, ""baz""',
      ['Hello "Foo bar" World'],
      TRUE,
    ];
    $tests['unquoted word within escaped, unquoted'] = [
      'Hello ""Foo bar"" World, baz',
      ['Hello "Foo bar" World', 'baz'],
      FALSE,
    ];
    $tests['quoted, words, escaped words, word'] = [
      '"Hello ""Foo bar"" World, baz"',
      ['Hello "Foo bar" World, baz'],
      FALSE,
    ];
    // Two quotes should not get escaped, creates empty tag.
    $tests['quoted, empty, unquoted'] = [
      '"",hello',
      ['hello'],
      FALSE,
    ];
    $tests['literal double quote, missing trailing quote'] = [
      '"""',
      [],
      TRUE,
    ];
    $tests['quoted, starts with quoted'] = [
      '"""Hello"',
      ['"Hello'],
      FALSE,
    ];
    $tests['quoted, escaped, missing comma'] = [
      '""""Hello""""',
      ['"'],
      TRUE,
    ];
    $tests['Escaped quote, tag'] = [
      '"""",hello',
      ['"', 'hello'],
      FALSE,
    ];
    $tests['quoted, double escaped, missing trailing quote'] = [
      '""""",hello',
      [],
      TRUE,
    ];
    $tests['quoted, two escaped, comma, tag'] = [
      '"""""",hello',
      ['""', 'hello'],
      FALSE,
    ];
    $tests['quoted, two escaped, word, two escaped'] = [
      '"""""Hello"""""',
      ['""Hello""'],
      FALSE,
    ];
    $tests['unquoted, two words, escaped'] = [
      'hello ""world""',
      ['hello "world"'],
      FALSE,
    ];
    $tests['word, escaped, unexpected quote'] = [
      'hello ""world"',
      [],
      TRUE,
    ];
    $tests['whitespace around unquoted, whitespace around quoted'] = [
      '    hello   ,    "world"    ',
      ['hello', 'world'],
      FALSE,
    ];
    $tests['quoted, escaped quotes, escaped quotes on end'] = [
      '"Hello world ""Foo bar"""',
      ['Hello world "Foo bar"'],
      FALSE,
    ];
    $tests['quoted, inner commas'] = [
      '"Hello, foo bar, World"',
      ['Hello, foo bar, World'],
      FALSE,
    ];

    return $tests;
  }

}
