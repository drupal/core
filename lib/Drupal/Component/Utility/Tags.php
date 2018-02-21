<?php

namespace Drupal\Component\Utility;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a class that can explode and implode tags.
 *
 * @ingroup utility
 */
class Tags {

  /**
   * Explodes a string of tags into an array.
   *
   * @param string $tags
   *   A string to explode.
   *
   * @return array
   *   An array of tags.
   */
  public static function explode($tags) {
    // This regexp allows the following types of user input:
    // this, "somecompany, llc", "and ""this"" w,o.rks", foo bar
    $regexp = '%(?:^|,\ *)("(?>[^"]*)(?>""[^"]* )*"|(?: [^",]*))%x';
    preg_match_all($regexp, $tags, $matches);
    $typed_tags = array_unique($matches[1]);

    $tags = [];
    foreach ($typed_tags as $tag) {
      // If a user has escaped a term (to demonstrate that it is a group,
      // or includes a comma or quote character), we remove the escape
      // formatting so to save the term into the database as the user intends.
      $tag = trim(str_replace('""', '"', preg_replace('/^"(.*)"$/', '\1', $tag)));
      if ($tag != "") {
        $tags[] = $tag;
      }
    }

    return $tags;
  }

  /**
   * Encodes a tag string, taking care of special cases like commas and quotes.
   *
   * @param string $tag
   *   A tag string.
   *
   * @return string
   *   The encoded string.
   */
  public static function encode($tag) {
    if (strpos($tag, ',') !== FALSE || strpos($tag, '"') !== FALSE) {
      return '"' . str_replace('"', '""', $tag) . '"';
    }
    return $tag;
  }

  /**
   * Implodes an array of tags into a string.
   *
   * @param array $tags
   *   An array of tags.
   *
   * @return string
   *   The imploded string.
   */
  public static function implode($tags) {
    $encoded_tags = [];
    foreach ($tags as $tag) {
      $encoded_tags[] = self::encode($tag);
    }
    return implode(', ', $encoded_tags);
  }

  public static function explodeWithErrors($string) {
    $errors = [];
    $tags = [];

    while (strlen($string) > 0) {
      preg_match('/^\s*(")/', $string, $matches, PREG_OFFSET_CAPTURE);
      $inQuotes = count($matches) != 0;
      if ($inQuotes) {
        $first_quote_position = $matches[1][1];
        $string = substr($string, $first_quote_position + 1);

        // Find first single quote.
        preg_match('/(?:^|[^"])(?:"")*(")(?:[^"]|$)/', $string, $matches, PREG_OFFSET_CAPTURE);
        $end_quote_position = $matches[1][1] ?? NULL;
        if (!isset($end_quote_position)) {
          $errors[] = new TranslatableMarkup('No ending quote character found.');
          break;
        }

        $tag = substr($string, 0, $end_quote_position);
        $tags[] = $tag;
        $string = substr($string, $end_quote_position + 1);

        // Next char should be whitespace then comma or end of string
        // Otherwise error: "Found text after $lasttag, expected comma or end of text."
        preg_match('/^\s*([,]|$)/', $string, $matches, PREG_OFFSET_CAPTURE);
        $nextChar = $matches[1][0] ?? NULL;
        if ($nextChar === ',') {
          // Take off the comma.
          $string = substr($string, 1);
        }
        elseif ($nextChar === '') {
          // End of string. Finish.
          break;
        }
        else {
          $errors[] = new TranslatableMarkup('Unexpected text after "@tag". Expected comma or end of text. Found @unexpected.', [
            '@tag' => $tag,
            '@unexpected' => substr($string, 0, 10),
          ]);
          break;
        }
      }
      else {
        $end_position = strpos($string, ',');
        $end_position = $end_position !== FALSE ? $end_position : strlen($string);

        $tag = substr($string, 0, $end_position);
        $string = substr($string, $end_position + 1);
        // Determine if there are any single quote characters.
        preg_match('/[^"](?:"")*(")(?:[^"]|$)/', $tag, $matches);
        if (!count($matches)) {
          $tags[] = trim($tag);
        }
        else {
          $errors[] = new TranslatableMarkup('Unexpected quote character found');
          break;
        }
      }
    }

    // Replace quote pairs with singles.
    $tags = array_map(
      function ($item) {
        return str_replace('""', '"', $item);
      },
      $tags
    );

    // Remove zero length.
    $tags = array_filter($tags);
    // Remove duplicates.
    $tags = array_unique($tags);
    // Reset keys.
    $tags = array_values($tags);

    return [
      'tags' => $tags,
      'errors' => $errors,
    ];
  }

}
