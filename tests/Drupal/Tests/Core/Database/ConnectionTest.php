<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Composer\Autoload\ClassLoader;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\StatementPrefetch;
use Drupal\Core\Database\StatementPrefetchIterator;
use Drupal\Tests\Core\Database\Stub\StubConnection;
use Drupal\Tests\Core\Database\Stub\StubPDO;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Connection class.
 *
 * @coversDefaultClass \Drupal\Core\Database\Connection
 * @group Database
 */
class ConnectionTest extends UnitTestCase {

  /**
   * Data provider for testPrefixRoundTrip().
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - Arguments to pass to Connection::setPrefix().
   *   - Expected result from Connection::tablePrefix().
   */
  public static function providerPrefixRoundTrip() {
    return [
      [
        [
          '' => 'test_',
        ],
        'test_',
      ],
      [
        [
          'fooTable' => 'foo_',
          'barTable' => 'foo_',
        ],
        'foo_',
      ],
    ];
  }

  /**
   * Exercise setPrefix() and tablePrefix().
   *
   * @dataProvider providerPrefixRoundTrip
   */
  public function testPrefixRoundTrip($expected, $prefix_info): void {
    $mock_pdo = $this->createMock('Drupal\Tests\Core\Database\Stub\StubPDO');
    $connection = new StubConnection($mock_pdo, []);

    // setPrefix() is protected, so we make it accessible with reflection.
    $reflection = new \ReflectionClass('Drupal\Tests\Core\Database\Stub\StubConnection');
    $set_prefix = $reflection->getMethod('setPrefix');

    // Set the prefix data.
    $set_prefix->invokeArgs($connection, [$prefix_info]);
    // Check the round-trip.
    foreach ($expected as $table => $prefix) {
      $this->assertEquals($prefix, $connection->getPrefix());
    }
  }

  /**
   * Data provider for testPrefixTables().
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - Expected result.
   *   - Table prefix.
   *   - Query to be prefixed.
   *   - Quote identifier.
   */
  public static function providerTestPrefixTables() {
    return [
      [
        'SELECT * FROM test_table',
        'test_',
        'SELECT * FROM {table}',
        ['', ''],
      ],
      [
        'SELECT * FROM "test_table"',
        'test_',
        'SELECT * FROM {table}',
        ['"', '"'],
      ],
      [
        "SELECT * FROM 'test_table'",
        'test_',
        'SELECT * FROM {table}',
        ["'", "'"],
      ],
      [
        "SELECT * FROM [test_table]",
        'test_',
        'SELECT * FROM {table}',
        ['[', ']'],
      ],
    ];
  }

  /**
   * Exercise the prefixTables() method.
   *
   * @dataProvider providerTestPrefixTables
   */
  public function testPrefixTables($expected, $prefix_info, $query, array $quote_identifier = ['"', '"']): void {
    $mock_pdo = $this->createMock('Drupal\Tests\Core\Database\Stub\StubPDO');
    $connection = new StubConnection($mock_pdo, ['prefix' => $prefix_info], $quote_identifier);
    $this->assertEquals($expected, $connection->prefixTables($query));
  }

  /**
   * Data provider for testGetDriverClass().
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - Expected namespaced class name.
   *   - Namespace.
   *   - Class name without namespace.
   */
  public static function providerGetDriverClass() {
    return [
      [
        'nonexistent_class',
        '\\',
        'nonexistent_class',
      ],
      [
        'Drupal\Tests\Core\Database\Stub\Select',
        NULL,
        'Select',
      ],
      // Tests with the CoreFake database driver. This driver has no custom
      // driver classes.
      [
        'Drupal\Core\Database\Query\Condition',
        'Drupal\CoreFake\Driver\Database\CoreFake',
        'Condition',
      ],
      [
        'Drupal\Core\Database\Query\Delete',
        'Drupal\CoreFake\Driver\Database\CoreFake',
        'Delete',
      ],
      [
        'Drupal\Core\Database\ExceptionHandler',
        'Drupal\CoreFake\Driver\Database\CoreFake',
        'ExceptionHandler',
      ],
      [
        'Drupal\Core\Database\Query\Insert',
        'Drupal\CoreFake\Driver\Database\CoreFake',
        'Insert',
      ],
      [
        'Drupal\Core\Database\Query\Merge',
        'Drupal\CoreFake\Driver\Database\CoreFake',
        'Merge',
      ],
      [
        'PagerSelectExtender',
        'Drupal\CoreFake\Driver\Database\CoreFake',
        'PagerSelectExtender',
      ],
      [
        'Drupal\Core\Database\Schema',
        'Drupal\CoreFake\Driver\Database\CoreFake',
        'Schema',
      ],
      [
        'SearchQuery',
        'Drupal\CoreFake\Driver\Database\CoreFake',
        'SearchQuery',
      ],
      [
        'Drupal\Core\Database\Query\Select',
        'Drupal\CoreFake\Driver\Database\CoreFake',
        'Select',
      ],
      [
        'Drupal\Core\Database\Transaction',
        'Drupal\CoreFake\Driver\Database\CoreFake',
        'Transaction',
      ],
      [
        'TableSortExtender',
        'Drupal\CoreFake\Driver\Database\CoreFake',
        'TableSortExtender',
      ],
      [
        'Drupal\Core\Database\Query\Truncate',
        'Drupal\CoreFake\Driver\Database\CoreFake',
        'Truncate',
      ],
      [
        'Drupal\Core\Database\Query\Update',
        'Drupal\CoreFake\Driver\Database\CoreFake',
        'Update',
      ],
      [
        'Drupal\Core\Database\Query\Upsert',
        'Drupal\CoreFake\Driver\Database\CoreFake',
        'Upsert',
      ],
      // Tests with the CoreFakeWithAllCustomClasses database driver. This
      // driver has custom driver classes for all classes.
      [
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses\Condition',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        'Condition',
      ],
      [
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses\Delete',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        'Delete',
      ],
      [
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses\ExceptionHandler',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        'ExceptionHandler',
      ],
      [
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses\Insert',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        'Insert',
      ],
      [
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses\Merge',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        'Merge',
      ],
      [
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses\PagerSelectExtender',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        'PagerSelectExtender',
      ],
      [
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses\Schema',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        'Schema',
      ],
      [
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses\SearchQuery',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        'SearchQuery',
      ],
      [
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses\Select',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        'Select',
      ],
      [
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses\TableSortExtender',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        'TableSortExtender',
      ],
      [
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses\Transaction',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        'Transaction',
      ],
      [
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses\Truncate',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        'Truncate',
      ],
      [
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses\Update',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        'Update',
      ],
      [
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses\Upsert',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        'Upsert',
      ],
      [
        'Drupal\Core\Database\Query\PagerSelectExtender',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        'Drupal\Core\Database\Query\PagerSelectExtender',
      ],
      [
        '\Drupal\Core\Database\Query\PagerSelectExtender',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        '\Drupal\Core\Database\Query\PagerSelectExtender',
      ],
      [
        'Drupal\Core\Database\Query\TableSortExtender',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        'Drupal\Core\Database\Query\TableSortExtender',
      ],
      [
        '\Drupal\Core\Database\Query\TableSortExtender',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        '\Drupal\Core\Database\Query\TableSortExtender',
      ],
      [
        'Drupal\search\SearchQuery',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        'Drupal\search\SearchQuery',
      ],
      [
        '\Drupal\search\SearchQuery',
        'Drupal\core_fake\Driver\Database\CoreFakeWithAllCustomClasses',
        '\Drupal\search\SearchQuery',
      ],
    ];
  }

  /**
   * @covers ::getDriverClass
   * @dataProvider providerGetDriverClass
   * @group legacy
   */
  public function testGetDriverClass($expected, $namespace, $class): void {
    $additional_class_loader = new ClassLoader();
    $additional_class_loader->addPsr4("Drupal\\core_fake\\Driver\\Database\\CoreFake\\", __DIR__ . "/../../../../../tests/fixtures/database_drivers/module/core_fake/src/Driver/Database/CoreFake");
    $additional_class_loader->addPsr4("Drupal\\core_fake\\Driver\\Database\\CoreFakeWithAllCustomClasses\\", __DIR__ . "/../../../../../tests/fixtures/database_drivers/module/core_fake/src/Driver/Database/CoreFakeWithAllCustomClasses");
    $additional_class_loader->register(TRUE);

    $mock_pdo = $this->createMock('Drupal\Tests\Core\Database\Stub\StubPDO');
    $connection = new StubConnection($mock_pdo, ['namespace' => $namespace]);
    match($class) {
      'Install\\Tasks',
      'ExceptionHandler',
      'Select',
      'Insert',
      'Merge',
      'Upsert',
      'Update',
      'Delete',
      'Truncate',
      'Schema',
      'Condition',
      'Transaction' => $this->expectDeprecation('Calling Drupal\\Core\\Database\\Connection::getDriverClass() for \'' . $class . '\' is deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use standard autoloading in the methods that return database operations. See https://www.drupal.org/node/3217534'),
      default => NULL,
    };
    $this->assertEquals($expected, $connection->getDriverClass($class));
  }

  /**
   * Data provider for testSchema().
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - Expected namespaced class of schema object.
   *   - Driver for PDO connection.
   *   - Namespace for connection.
   */
  public static function providerSchema() {
    return [
      [
        'Drupal\\Tests\\Core\\Database\\Stub\\Driver\\Schema',
        'stub',
        'Drupal\\Tests\\Core\\Database\\Stub\\Driver',
      ],
    ];
  }

  /**
   * Tests Connection::schema().
   *
   * @dataProvider providerSchema
   */
  public function testSchema($expected, $driver, $namespace): void {
    $mock_pdo = $this->createMock('Drupal\Tests\Core\Database\Stub\StubPDO');
    $connection = new StubConnection($mock_pdo, ['namespace' => $namespace]);
    $connection->driver = $driver;
    $this->assertInstanceOf($expected, $connection->schema());
  }

  /**
   * Data provider for testMakeComments().
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - Expected filtered comment.
   *   - Arguments for Connection::makeComment().
   */
  public static function providerMakeComments() {
    return [
      [
        '/*  */ ',
        [''],
      ],
      [
        '/* Exploit  *  / DROP TABLE node. -- */ ',
        ['Exploit * / DROP TABLE node; --'],
      ],
      [
        '/* Exploit  *  / DROP TABLE node. --. another comment */ ',
        ['Exploit * / DROP TABLE node; --', 'another comment'],
      ],
    ];
  }

  /**
   * Tests Connection::makeComments().
   *
   * @dataProvider providerMakeComments
   */
  public function testMakeComments($expected, $comment_array): void {
    $mock_pdo = $this->createMock('Drupal\Tests\Core\Database\Stub\StubPDO');
    $connection = new StubConnection($mock_pdo, []);
    $this->assertEquals($expected, $connection->makeComment($comment_array));
  }

  /**
   * Data provider for testFilterComments().
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - Expected filtered comment.
   *   - Comment to filter.
   */
  public static function providerFilterComments() {
    return [
      ['', ''],
      ['Exploit  *  / DROP TABLE node. --', 'Exploit * / DROP TABLE node; --'],
      ['Exploit  * / DROP TABLE node. --', 'Exploit */ DROP TABLE node; --'],
    ];
  }

  /**
   * Tests Connection::filterComments().
   *
   * @dataProvider providerFilterComments
   */
  public function testFilterComments($expected, $comment): void {
    $mock_pdo = $this->createMock('Drupal\Tests\Core\Database\Stub\StubPDO');
    $connection = new StubConnection($mock_pdo, []);

    // filterComment() is protected, so we make it accessible with reflection.
    $reflection = new \ReflectionClass('Drupal\Tests\Core\Database\Stub\StubConnection');
    $filter_comment = $reflection->getMethod('filterComment');

    $this->assertEquals(
      $expected,
      $filter_comment->invokeArgs($connection, [$comment])
    );
  }

  /**
   * Data provider for testEscapeTable.
   *
   * @return array
   *   An indexed array of where each value is an array of arguments to pass to
   *   testEscapeField. The first value is the expected value, and the second
   *   value is the value to test.
   */
  public static function providerEscapeTables() {
    return [
      ['nocase', 'nocase'],
      ['camelCase', 'camelCase'],
      ['backtick', '`backtick`', ['`', '`']],
      ['brackets', '[brackets]', ['[', ']']],
      ['camelCase', '"camelCase"'],
      ['camelCase', 'camel/Case'],
      // Sometimes, table names are following the pattern database.schema.table.
      ['camelCase.nocase.nocase', 'camelCase.nocase.nocase'],
      ['nocase.camelCase.nocase', 'nocase.camelCase.nocase'],
      ['nocase.nocase.camelCase', 'nocase.nocase.camelCase'],
      ['camelCase.camelCase.camelCase', 'camelCase.camelCase.camelCase'],
    ];
  }

  /**
   * @covers ::escapeTable
   * @dataProvider providerEscapeTables
   */
  public function testEscapeTable($expected, $name, array $identifier_quote = ['"', '"']): void {
    $mock_pdo = $this->createMock(StubPDO::class);
    $connection = new StubConnection($mock_pdo, [], $identifier_quote);

    $this->assertEquals($expected, $connection->escapeTable($name));
  }

  /**
   * Data provider for testEscapeAlias.
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - Expected escaped string.
   *   - String to escape.
   */
  public static function providerEscapeAlias() {
    return [
      ['!nocase!', 'nocase', ['!', '!']],
      ['`backtick`', 'backtick', ['`', '`']],
      ['nocase', 'nocase', ['', '']],
      ['[brackets]', 'brackets', ['[', ']']],
      ['"camelCase"', '"camelCase"'],
      ['"camelCase"', 'camelCase'],
      ['"camelCase"', 'camel.Case'],
    ];
  }

  /**
   * @covers ::escapeAlias
   * @dataProvider providerEscapeAlias
   */
  public function testEscapeAlias($expected, $name, array $identifier_quote = ['"', '"']): void {
    $mock_pdo = $this->createMock(StubPDO::class);
    $connection = new StubConnection($mock_pdo, [], $identifier_quote);

    $this->assertEquals($expected, $connection->escapeAlias($name));
  }

  /**
   * Data provider for testEscapeField.
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - Expected escaped string.
   *   - String to escape.
   */
  public static function providerEscapeFields() {
    return [
      ['/title/', 'title', ['/', '/']],
      ['`backtick`', 'backtick', ['`', '`']],
      ['test.title', 'test.title', ['', '']],
      ['"isDefaultRevision"', 'isDefaultRevision'],
      ['"isDefaultRevision"', '"isDefaultRevision"'],
      ['"entity_test"."isDefaultRevision"', 'entity_test.isDefaultRevision'],
      ['"entity_test"."isDefaultRevision"', '"entity_test"."isDefaultRevision"'],
      ['"entityTest"."isDefaultRevision"', '"entityTest"."isDefaultRevision"'],
      ['"entityTest"."isDefaultRevision"', 'entityTest.isDefaultRevision'],
      ['[entityTest].[isDefaultRevision]', 'entityTest.isDefaultRevision', ['[', ']']],
    ];
  }

  /**
   * @covers ::escapeField
   * @dataProvider providerEscapeFields
   */
  public function testEscapeField($expected, $name, array $identifier_quote = ['"', '"']): void {
    $mock_pdo = $this->createMock(StubPDO::class);
    $connection = new StubConnection($mock_pdo, [], $identifier_quote);

    $this->assertEquals($expected, $connection->escapeField($name));
  }

  /**
   * Data provider for testEscapeDatabase.
   *
   * @return array
   *   An indexed array of where each value is an array of arguments to pass to
   *   testEscapeField. The first value is the expected value, and the second
   *   value is the value to test.
   */
  public static function providerEscapeDatabase() {
    return [
      ['/name/', 'name', ['/', '/']],
      ['`backtick`', 'backtick', ['`', '`']],
      ['anything', 'any.thing', ['', '']],
      ['"name"', 'name'],
      ['[name]', 'name', ['[', ']']],
    ];
  }

  /**
   * @covers ::escapeDatabase
   * @dataProvider providerEscapeDatabase
   */
  public function testEscapeDatabase($expected, $name, array $identifier_quote = ['"', '"']): void {
    $mock_pdo = $this->createMock(StubPDO::class);
    $connection = new StubConnection($mock_pdo, [], $identifier_quote);

    $this->assertEquals($expected, $connection->escapeDatabase($name));
  }

  /**
   * @covers ::__construct
   */
  public function testIdentifierQuotesAssertCount(): void {
    $this->expectException(\AssertionError::class);
    $this->expectExceptionMessage('\Drupal\Core\Database\Connection::$identifierQuotes must contain 2 string values');
    $mock_pdo = $this->createMock(StubPDO::class);
    new StubConnection($mock_pdo, [], ['"']);
  }

  /**
   * @covers ::__construct
   */
  public function testIdentifierQuotesAssertString(): void {
    $this->expectException(\AssertionError::class);
    $this->expectExceptionMessage('\Drupal\Core\Database\Connection::$identifierQuotes must contain 2 string values');
    $mock_pdo = $this->createMock(StubPDO::class);
    new StubConnection($mock_pdo, [], [0, '1']);
  }

  /**
   * @covers ::__construct
   */
  public function testNamespaceDefault(): void {
    $mock_pdo = $this->createMock(StubPDO::class);
    $connection = new StubConnection($mock_pdo, []);
    $this->assertSame('Drupal\Tests\Core\Database\Stub', $connection->getConnectionOptions()['namespace']);
  }

  /**
   * Test rtrim() of query strings.
   *
   * @dataProvider provideQueriesToTrim
   */
  public function testQueryTrim($expected, $query, $options): void {
    $mock_pdo = $this->getMockBuilder(StubPdo::class)->getMock();
    $connection = new StubConnection($mock_pdo, []);

    $preprocess_method = new \ReflectionMethod($connection, 'preprocessStatement');
    $this->assertSame($expected, $preprocess_method->invoke($connection, $query, $options));
  }

  /**
   * Data provider for testQueryTrim().
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - Expected trimmed query.
   *   - Padded query.
   *   - Query options.
   */
  public static function provideQueriesToTrim() {
    return [
      'remove_non_breaking_space' => [
        'SELECT * FROM test',
        "SELECT * FROM test\xA0",
        [],
      ],
      'remove_ordinary_space' => [
        'SELECT * FROM test',
        'SELECT * FROM test ',
        [],
      ],
      'remove_semicolon' => [
        'SELECT * FROM test',
        'SELECT * FROM test;',
        [],
      ],
      'keep_trailing_semicolon' => [
        'SELECT * FROM test;',
        'SELECT * FROM test;',
        ['allow_delimiter_in_query' => TRUE],
      ],
      'remove_semicolon_with_whitespace' => [
        'SELECT * FROM test',
        'SELECT * FROM test; ',
        [],
      ],
      'keep_trailing_semicolon_with_whitespace' => [
        'SELECT * FROM test;',
        'SELECT * FROM test; ',
        ['allow_delimiter_in_query' => TRUE],
      ],
    ];
  }

  /**
   * Tests that the proper caller is retrieved from the backtrace.
   *
   * @covers ::findCallerFromDebugBacktrace
   * @covers ::removeDatabaseEntriesFromDebugBacktrace
   * @covers ::getDebugBacktrace
   */
  public function testFindCallerFromDebugBacktrace(): void {
    Database::addConnectionInfo('default', 'default', [
      'driver' => 'test',
      'namespace' => 'Drupal\Tests\Core\Database\Stub',
    ]);
    $connection = new StubConnection($this->createMock(StubPDO::class), []);
    $result = $connection->findCallerFromDebugBacktrace();
    $this->assertSame([
      'file' => __FILE__,
      'line' => __LINE__ - 3,
      'function' => 'testFindCallerFromDebugBacktrace',
      'class' => 'Drupal\Tests\Core\Database\ConnectionTest',
      'type' => '->',
      'args' => [],
    ], $result);
  }

  /**
   * Tests that a log called by a custom database driver returns proper caller.
   *
   * @param string $driver_namespace
   *   The driver namespace to be tested.
   * @param array $stack
   *   A test debug_backtrace stack.
   * @param array $expected_entry
   *   The expected stack entry.
   *
   * @covers ::findCallerFromDebugBacktrace
   * @covers ::removeDatabaseEntriesFromDebugBacktrace
   *
   * @dataProvider providerMockedBacktrace
   *
   * @group legacy
   */
  public function testFindCallerFromDebugBacktraceWithMockedBacktrace(string $driver_namespace, array $stack, array $expected_entry): void {
    $mock_builder = $this->getMockBuilder(StubConnection::class);
    $connection = $mock_builder
      ->onlyMethods(['getDebugBacktrace', 'getConnectionOptions'])
      ->setConstructorArgs([$this->createMock(StubPDO::class), []])
      ->getMock();
    $connection->expects($this->once())
      ->method('getConnectionOptions')
      ->willReturn([
        'driver' => 'test',
        'namespace' => $driver_namespace,
      ]);
    $connection->expects($this->once())
      ->method('getDebugBacktrace')
      ->willReturn($stack);

    $result = $connection->findCallerFromDebugBacktrace();
    $this->assertEquals($expected_entry, $result);
  }

  /**
   * Provides data for testFindCallerFromDebugBacktraceWithMockedBacktrace.
   *
   * @return array[]
   *   A associative array of simple arrays, each having the following elements:
   *   - the contrib driver PHP namespace
   *   - a test debug_backtrace stack
   *   - the stack entry expected to be returned.
   *
   * @see ::testFindCallerFromDebugBacktraceWithMockedBacktrace()
   */
  public static function providerMockedBacktrace(): array {
    $stack = [
      [
        'file' => '/var/www/core/lib/Drupal/Core/Database/Log.php',
        'line' => 125,
        'function' => 'findCaller',
        'class' => 'Drupal\\Core\\Database\\Log',
        'object' => 'test',
        'type' => '->',
        'args' => [
          0 => 'test',
        ],
      ],
      [
        'file' => '/var/www/libraries/test/lib/Statement.php',
        'line' => 264,
        'function' => 'log',
        'class' => 'Drupal\\Core\\Database\\Log',
        'object' => 'test',
        'type' => '->',
        'args' => [
          0 => 'test',
        ],
      ],
      [
        'file' => '/var/www/libraries/test/lib/Connection.php',
        'line' => 213,
        'function' => 'execute',
        'class' => 'Drupal\\Driver\\Database\\dbal\\Statement',
        'object' => 'test',
        'type' => '->',
        'args' => [
          0 => 'test',
        ],
      ],
      [
        'file' => '/var/www/core/tests/Drupal/KernelTests/Core/Database/LoggingTest.php',
        'line' => 23,
        'function' => 'query',
        'class' => 'Drupal\\Driver\\Database\\dbal\\Connection',
        'object' => 'test',
        'type' => '->',
        'args' => [
          0 => 'test',
        ],
      ],
      [
        'file' => '/var/www/vendor/phpunit/phpunit/src/Framework/TestCase.php',
        'line' => 1154,
        'function' => 'testEnableLogging',
        'class' => 'Drupal\\KernelTests\\Core\\Database\\LoggingTest',
        'object' => 'test',
        'type' => '->',
        'args' => [
          0 => 'test',
        ],
      ],
      [
        'file' => '/var/www/vendor/phpunit/phpunit/src/Framework/TestCase.php',
        'line' => 842,
        'function' => 'runTest',
        'class' => 'PHPUnit\\Framework\\TestCase',
        'object' => 'test',
        'type' => '->',
        'args' => [
          0 => 'test',
        ],
      ],
      [
        'file' => '/var/www/vendor/phpunit/phpunit/src/Framework/TestResult.php',
        'line' => 693,
        'function' => 'runBare',
        'class' => 'PHPUnit\\Framework\\TestCase',
        'object' => 'test',
        'type' => '->',
        'args' => [
          0 => 'test',
        ],
      ],
      [
        'file' => '/var/www/vendor/phpunit/phpunit/src/Framework/TestCase.php',
        'line' => 796,
        'function' => 'run',
        'class' => 'PHPUnit\\Framework\\TestResult',
        'object' => 'test',
        'type' => '->',
        'args' => [
          0 => 'test',
        ],
      ],
      [
        'file' => 'Standard input code',
        'line' => 57,
        'function' => 'run',
        'class' => 'PHPUnit\\Framework\\TestCase',
        'object' => 'test',
        'type' => '->',
        'args' => [
          0 => 'test',
        ],
      ],
      [
        'file' => 'Standard input code',
        'line' => 111,
        'function' => '__phpunit_run_isolated_test',
        'args' => [
          0 => 'test',
        ],
      ],
    ];

    return [
      // Test that if the driver namespace is in the stack trace, the first
      // non-database entry is returned.
      'contrib driver namespace' => [
        'Drupal\\Driver\\Database\\dbal',
        $stack,
        [
          'class' => 'Drupal\\KernelTests\\Core\\Database\\LoggingTest',
          'function' => 'testEnableLogging',
          'file' => '/var/www/core/tests/Drupal/KernelTests/Core/Database/LoggingTest.php',
          'line' => 23,
          'type' => '->',
          'args' => [
            0 => 'test',
          ],
        ],
      ],
      // Extreme case, should not happen at normal runtime - if the driver
      // namespace is not in the stack trace, the first entry to a method
      // in core database namespace is returned.
      'missing driver namespace' => [
        'Drupal\\Driver\\Database\\fake',
        $stack,
        [
          'class' => 'Drupal\\Driver\\Database\\dbal\\Statement',
          'function' => 'execute',
          'file' => '/var/www/libraries/test/lib/Statement.php',
          'line' => 264,
          'type' => '->',
          'args' => [
            0 => 'test',
          ],
        ],
      ],
    ];
  }

  /**
   * Tests deprecation of the StatementWrapper class.
   *
   * @group legacy
   */
  public function testStatementWrapperDeprecation(): void {
    $this->expectDeprecation('\\Drupal\\Core\\Database\\StatementWrapper is deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use \\Drupal\\Core\\Database\\StatementWrapperIterator instead. See https://www.drupal.org/node/3265938');
    $mock_pdo = $this->createMock(StubPDO::class);
    $connection = new StubConnection($mock_pdo, []);
    $this->expectError();
    $connection->prepareStatement('boing', []);
  }

  /**
   * Tests deprecation of the StatementPrefetch class.
   *
   * @group legacy
   */
  public function testStatementPrefetchDeprecation(): void {
    $this->expectDeprecation('\\Drupal\\Core\\Database\\StatementPrefetch is deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use \Drupal\Core\Database\StatementPrefetchIterator instead. See https://www.drupal.org/node/3265938');
    $mockPdo = $this->createMock(StubPDO::class);
    $mockConnection = new StubConnection($mockPdo, []);
    $statement = new StatementPrefetch($mockPdo, $mockConnection, '');
    $this->assertInstanceOf(StatementPrefetch::class, $statement);
  }

  /**
   * Provides data for testSupportedFetchModes.
   *
   * @return array
   *   An associative array of simple arrays, each having the following
   *   elements:
   *   - a PDO fetch mode.
   */
  public static function providerSupportedFetchModes(): array {
    return [
      'FETCH_ASSOC' => [\PDO::FETCH_ASSOC],
      'FETCH_CLASS' => [\PDO::FETCH_CLASS],
      'FETCH_CLASS | FETCH_PROPS_LATE' => [\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE],
      'FETCH_COLUMN' => [\PDO::FETCH_COLUMN],
      'FETCH_NUM' => [\PDO::FETCH_NUM],
      'FETCH_OBJ' => [\PDO::FETCH_OBJ],
    ];
  }

  /**
   * Tests supported fetch modes.
   *
   * @dataProvider providerSupportedFetchModes
   */
  public function testSupportedFetchModes(int $mode): void {
    $mockPdo = $this->createMock(StubPDO::class);
    $mockConnection = new StubConnection($mockPdo, []);
    $statement = new StatementPrefetchIterator($mockPdo, $mockConnection, '');
    $this->assertInstanceOf(StatementPrefetchIterator::class, $statement);
    $statement->setFetchMode($mode);
  }

  /**
   * Provides data for testDeprecatedFetchModes.
   *
   * @return array
   *   An associative array of simple arrays, each having the following
   *   elements:
   *   - a PDO fetch mode.
   */
  public static function providerDeprecatedFetchModes(): array {
    return [
      'FETCH_DEFAULT' => [\PDO::FETCH_DEFAULT],
      'FETCH_LAZY' => [\PDO::FETCH_LAZY],
      'FETCH_BOTH' => [\PDO::FETCH_BOTH],
      'FETCH_BOUND' => [\PDO::FETCH_BOUND],
      'FETCH_INTO' => [\PDO::FETCH_INTO],
      'FETCH_FUNC' => [\PDO::FETCH_FUNC],
      'FETCH_NAMED' => [\PDO::FETCH_NAMED],
      'FETCH_KEY_PAIR' => [\PDO::FETCH_KEY_PAIR],
      'FETCH_CLASS | FETCH_CLASSTYPE' => [\PDO::FETCH_CLASS | \PDO::FETCH_CLASSTYPE],
    ];
  }

  /**
   * Tests deprecated fetch modes.
   *
   * @todo in drupal:11.0.0, do not remove this test but convert it to expect
   *   exceptions instead of deprecations.
   *
   * @dataProvider providerDeprecatedFetchModes
   *
   * @group legacy
   */
  public function testDeprecatedFetchModes(int $mode): void {
    $this->expectDeprecation('Fetch mode %A is deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use supported modes only. See https://www.drupal.org/node/3377999');
    $mockPdo = $this->createMock(StubPDO::class);
    $mockConnection = new StubConnection($mockPdo, []);
    $statement = new StatementPrefetchIterator($mockPdo, $mockConnection, '');
    $this->assertInstanceOf(StatementPrefetchIterator::class, $statement);
    $statement->setFetchMode($mode);
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    parent::tearDown();

    // Removes the default connection added by the
    // testFindCallerFromDebugBacktrace test.
    Database::removeConnection('default');
  }

}
