<?php

/**
 * @file
 * Installs the search_help module in a fixture database.
 */

declare(strict_types=1);

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Add search_help to core.extension config.
$extensions = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField();

if ($extensions) {
  $data = unserialize($extensions);
  $data['module']['search_help'] = 0;
  ksort($data['module']);
  $connection->update('config')
    ->fields(['data' => serialize($data)])
    ->condition('collection', '')
    ->condition('name', 'core.extension')
    ->execute();
}

// Add search_help schema version.
$connection->insert('key_value')
  ->fields([
    'value' => 'i:11000;',
    'collection' => 'system.schema',
    'name' => 'search_help',
  ])
  ->execute();
