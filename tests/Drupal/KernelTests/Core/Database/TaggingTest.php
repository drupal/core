<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\Query\SelectExtender;

/**
 * Tests the tagging capabilities of the Select builder.
 *
 * Tags are a way to flag queries for alter hooks so they know
 * what type of query it is, such as "node_access".
 *
 * @group Database
 */
class TaggingTest extends DatabaseTestBase {

  /**
   * Confirms that a query has a tag added to it.
   */
  public function testHasTag(): void {
    $query = $this->connection->select('test');
    $query->addField('test', 'name');
    $query->addField('test', 'age', 'age');

    $query->addTag('test');

    $this->assertTrue($query->hasTag('test'), 'hasTag() returned true.');
    $this->assertFalse($query->hasTag('other'), 'hasTag() returned false.');
  }

  /**
   * Tests query tagging "has all of these tags" functionality.
   */
  public function testHasAllTags(): void {
    $query = $this->connection->select('test');
    $query->addField('test', 'name');
    $query->addField('test', 'age', 'age');

    $query->addTag('test');
    $query->addTag('other');

    $this->assertTrue($query->hasAllTags('test', 'other'), 'hasAllTags() returned true.');
    $this->assertFalse($query->hasAllTags('test', 'stuff'), 'hasAllTags() returned false.');
  }

  /**
   * Tests query tagging "has at least one of these tags" functionality.
   */
  public function testHasAnyTag(): void {
    $query = $this->connection->select('test');
    $query->addField('test', 'name');
    $query->addField('test', 'age', 'age');

    $query->addTag('test');

    $this->assertTrue($query->hasAnyTag('test', 'other'), 'hasAnyTag() returned true.');
    $this->assertFalse($query->hasAnyTag('other', 'stuff'), 'hasAnyTag() returned false.');
  }

  /**
   * Confirms that an extended query has a tag added to it.
   */
  public function testExtenderHasTag(): void {
    $query = $this->connection->select('test')
      ->extend(SelectExtender::class);
    $query->addField('test', 'name');
    $query->addField('test', 'age', 'age');

    $query->addTag('test');

    $this->assertTrue($query->hasTag('test'), 'hasTag() returned true.');
    $this->assertFalse($query->hasTag('other'), 'hasTag() returned false.');
  }

  /**
   * Tests extended query tagging "has all of these tags" functionality.
   */
  public function testExtenderHasAllTags(): void {
    $query = $this->connection->select('test')
      ->extend(SelectExtender::class);
    $query->addField('test', 'name');
    $query->addField('test', 'age', 'age');

    $query->addTag('test');
    $query->addTag('other');

    $this->assertTrue($query->hasAllTags('test', 'other'), 'hasAllTags() returned true.');
    $this->assertFalse($query->hasAllTags('test', 'stuff'), 'hasAllTags() returned false.');
  }

  /**
   * Tests extended query tagging for "has at least one of these tags".
   */
  public function testExtenderHasAnyTag(): void {
    $query = $this->connection->select('test')
      ->extend(SelectExtender::class);
    $query->addField('test', 'name');
    $query->addField('test', 'age', 'age');

    $query->addTag('test');

    $this->assertTrue($query->hasAnyTag('test', 'other'), 'hasAnyTag() returned true.');
    $this->assertFalse($query->hasAnyTag('other', 'stuff'), 'hasAnyTag() returned false.');
  }

  /**
   * Tests that we can attach metadata to a query object.
   *
   * This is how we pass additional context to alter hooks.
   */
  public function testMetaData(): void {
    $query = $this->connection->select('test');
    $query->addField('test', 'name');
    $query->addField('test', 'age', 'age');

    $data = [
      'a' => 'A',
      'b' => 'B',
    ];

    $query->addMetaData('test', $data);

    $return = $query->getMetaData('test');
    $this->assertEquals($data, $return, 'Correct metadata returned.');

    $return = $query->getMetaData('not_here');
    $this->assertNull($return, 'Non-existent key returned NULL.');
  }

}
