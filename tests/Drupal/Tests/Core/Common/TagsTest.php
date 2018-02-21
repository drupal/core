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
   * Xyz
   *
   * @param string $string
   * @param array $tagsExpected
   * @param bool $hasError
   *
   * @dataProvider providerTestExplode
   */
  public function testExplodeXXX($string, $tagsExpected, $hasError) {
    $result = Tags::explode($string);
    $tags = $result['tags'];
    $errors = $result['errors'];

    $this->assertEquals($tagsExpected, $tags);
    $this->assertEquals($hasError, count($errors) > 0);
  }

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
      '"Jimmy "The Boss" Smith, Mr"',
      ['Jimmy '],
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
      'Jimmy "The Boss" Smith, Mr',
      [],
      TRUE,
    ];
    $tests['Quoted with no contents, missing comma'] = [
      '""Jimmy "The Boss" Smith, Mr"',
      [],
      TRUE,
    ];
    $tests['unquoted, unquoted, trailing escaped'] = [
      'Jimmy The Boss Smith, Mr""',
      ['Jimmy The Boss Smith', 'Mr"'],
      FALSE,
    ];
    $tests['unquoted, word within escaped, quoted, empty tag, unexpected character'] = [
      'Jimmy ""The Boss"" Smith, ""Mr""',
      ['Jimmy "The Boss" Smith'],
      TRUE,
    ];
    $tests['unquoted word within escaped, unquoted'] = [
      'Jimmy ""The Boss"" Smith, Mr',
      ['Jimmy "The Boss" Smith', 'Mr'],
      FALSE,
    ];
    $tests['quoted, words, escaped words, word'] = [
      '"Jimmy ""The Boss"" Smith, Mr"',
      ['Jimmy "The Boss" Smith, Mr'],
      FALSE,
    ];
    // Ensures the two quotes don't get escaped, rather create empty tag.
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
    $tests['Outerquotes, no whitespace, multiword, escaped quotes, escaped quotes on end'] = [
      '"Legendary Drupal mascot of doom: ""Druplicon"""',
      ['Legendary Drupal mascot of doom: "Druplicon"'],
      FALSE,
    ];
    $tests['Outerquotes, no whitespace, multiword, inner commas'] = [
      '"Drupal, although it rhymes with sloopal, is as awesome as a troopal!"',
      ['Drupal, although it rhymes with sloopal, is as awesome as a troopal!'],
      FALSE,
    ];

    return $tests;
  }

}
