<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\Database;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for database URL to/from database connection array conversions.
 *
 * These tests run in isolation since we don't want the database static to
 * affect other tests.
 *
 * @coversDefaultClass \Drupal\Core\Database\Database
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * @group Database
 */
class UrlConversionTest extends UnitTestCase {

  /**
   * @covers ::convertDbUrlToConnectionInfo
   *
   * @dataProvider providerConvertDbUrlToConnectionInfo
   */
  public function testDbUrlToConnectionConversion($url, $database_array, $include_test_drivers): void {
    $result = Database::convertDbUrlToConnectionInfo($url, $this->root, $include_test_drivers);
    $this->assertEquals($database_array, $result);
  }

  /**
   * Data provider for testDbUrlToConnectionConversion().
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - url: The full URL string to be tested.
   *   - database_array: An array containing the expected results.
   */
  public static function providerConvertDbUrlToConnectionInfo() {
    $root = dirname(__FILE__, 7);
    return [
      'MySql without prefix' => [
        'mysql://test_user:test_pass@test_host:3306/test_database',
        [
          'driver' => 'mysql',
          'username' => 'test_user',
          'password' => 'test_pass',
          'host' => 'test_host',
          'database' => 'test_database',
          'port' => 3306,
          'namespace' => 'Drupal\mysql\Driver\Database\mysql',
          'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
        ],
        FALSE,
      ],
      'SQLite, relative to root, without prefix' => [
        'sqlite://localhost/test_database',
        [
          'driver' => 'sqlite',
          'host' => 'localhost',
          'database' => $root . '/test_database',
          'namespace' => 'Drupal\sqlite\Driver\Database\sqlite',
          'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/',
        ],
        FALSE,
      ],
      'MySql with prefix' => [
        'mysql://test_user:test_pass@test_host:3306/test_database#bar',
        [
          'driver' => 'mysql',
          'username' => 'test_user',
          'password' => 'test_pass',
          'host' => 'test_host',
          'database' => 'test_database',
          'prefix' => 'bar',
          'port' => 3306,
          'namespace' => 'Drupal\mysql\Driver\Database\mysql',
          'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
        ],
        FALSE,
      ],
      'SQLite, relative to root, with prefix' => [
        'sqlite://localhost/test_database#foo',
        [
          'driver' => 'sqlite',
          'host' => 'localhost',
          'database' => $root . '/test_database',
          'prefix' => 'foo',
          'namespace' => 'Drupal\sqlite\Driver\Database\sqlite',
          'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/',
        ],
        FALSE,
      ],
      'SQLite, absolute path, without prefix' => [
        'sqlite://localhost//baz/test_database',
        [
          'driver' => 'sqlite',
          'host' => 'localhost',
          'database' => '/baz/test_database',
          'namespace' => 'Drupal\sqlite\Driver\Database\sqlite',
          'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/',
        ],
        FALSE,
      ],
      'MySQL contrib test driver without prefix' => [
        'DrivertestMysql://test_user:test_pass@test_host:3306/test_database?module=driver_test',
        [
          'driver' => 'DrivertestMysql',
          'username' => 'test_user',
          'password' => 'test_pass',
          'host' => 'test_host',
          'database' => 'test_database',
          'port' => 3306,
          'namespace' => 'Drupal\driver_test\Driver\Database\DrivertestMysql',
          'autoload' => 'core/modules/system/tests/modules/driver_test/src/Driver/Database/DrivertestMysql/',
          'dependencies' => [
            'mysql' => [
              'namespace' => 'Drupal\mysql',
              'autoload' => 'core/modules/mysql/src/',
            ],
            'pgsql' => [
              'namespace' => 'Drupal\pgsql',
              'autoload' => 'core/modules/pgsql/src/',
            ],
          ],
        ],
        TRUE,
      ],
      'MySQL contrib test driver with prefix' => [
        'DrivertestMysql://test_user:test_pass@test_host:3306/test_database?module=driver_test#bar',
        [
          'driver' => 'DrivertestMysql',
          'username' => 'test_user',
          'password' => 'test_pass',
          'host' => 'test_host',
          'database' => 'test_database',
          'prefix' => 'bar',
          'port' => 3306,
          'namespace' => 'Drupal\driver_test\Driver\Database\DrivertestMysql',
          'autoload' => 'core/modules/system/tests/modules/driver_test/src/Driver/Database/DrivertestMysql/',
          'dependencies' => [
            'mysql' => [
              'namespace' => 'Drupal\mysql',
              'autoload' => 'core/modules/mysql/src/',
            ],
            'pgsql' => [
              'namespace' => 'Drupal\pgsql',
              'autoload' => 'core/modules/pgsql/src/',
            ],
          ],
        ],
        TRUE,
      ],
      'PostgreSQL contrib test driver without prefix' => [
        'DrivertestPgsql://test_user:test_pass@test_host:5432/test_database?module=driver_test',
        [
          'driver' => 'DrivertestPgsql',
          'username' => 'test_user',
          'password' => 'test_pass',
          'host' => 'test_host',
          'database' => 'test_database',
          'port' => 5432,
          'namespace' => 'Drupal\driver_test\Driver\Database\DrivertestPgsql',
          'autoload' => 'core/modules/system/tests/modules/driver_test/src/Driver/Database/DrivertestPgsql/',
          'dependencies' => [
            'mysql' => [
              'namespace' => 'Drupal\mysql',
              'autoload' => 'core/modules/mysql/src/',
            ],
            'pgsql' => [
              'namespace' => 'Drupal\pgsql',
              'autoload' => 'core/modules/pgsql/src/',
            ],
          ],
        ],
        TRUE,
      ],
      'PostgreSQL contrib test driver with prefix' => [
        'DrivertestPgsql://test_user:test_pass@test_host:5432/test_database?module=driver_test#bar',
        [
          'driver' => 'DrivertestPgsql',
          'username' => 'test_user',
          'password' => 'test_pass',
          'host' => 'test_host',
          'database' => 'test_database',
          'prefix' => 'bar',
          'port' => 5432,
          'namespace' => 'Drupal\driver_test\Driver\Database\DrivertestPgsql',
          'autoload' => 'core/modules/system/tests/modules/driver_test/src/Driver/Database/DrivertestPgsql/',
          'dependencies' => [
            'mysql' => [
              'namespace' => 'Drupal\mysql',
              'autoload' => 'core/modules/mysql/src/',
            ],
            'pgsql' => [
              'namespace' => 'Drupal\pgsql',
              'autoload' => 'core/modules/pgsql/src/',
            ],
          ],
        ],
        TRUE,
      ],
      'MySql with a custom query parameter' => [
        'mysql://test_user:test_pass@test_host:3306/test_database?extra=value',
        [
          'driver' => 'mysql',
          'username' => 'test_user',
          'password' => 'test_pass',
          'host' => 'test_host',
          'database' => 'test_database',
          'port' => 3306,
          'namespace' => 'Drupal\mysql\Driver\Database\mysql',
          'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
        ],
        FALSE,
      ],
      'MySql with the module name mysql' => [
        'mysql://test_user:test_pass@test_host:3306/test_database?module=mysql',
        [
          'driver' => 'mysql',
          'username' => 'test_user',
          'password' => 'test_pass',
          'host' => 'test_host',
          'database' => 'test_database',
          'port' => 3306,
          'namespace' => 'Drupal\mysql\Driver\Database\mysql',
          'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
        ],
        FALSE,
      ],
      'PostgreSql without the module name set' => [
        'pgsql://test_user:test_pass@test_host/test_database',
        [
          'driver' => 'pgsql',
          'username' => 'test_user',
          'password' => 'test_pass',
          'host' => 'test_host',
          'database' => 'test_database',
          'namespace' => 'Drupal\pgsql\Driver\Database\pgsql',
          'autoload' => 'core/modules/pgsql/src/Driver/Database/pgsql/',
        ],
        FALSE,
      ],
      'PostgreSql with the module name pgsql' => [
        'pgsql://test_user:test_pass@test_host/test_database?module=pgsql',
        [
          'driver' => 'pgsql',
          'username' => 'test_user',
          'password' => 'test_pass',
          'host' => 'test_host',
          'database' => 'test_database',
          'namespace' => 'Drupal\pgsql\Driver\Database\pgsql',
          'autoload' => 'core/modules/pgsql/src/Driver/Database/pgsql/',
        ],
        FALSE,
      ],
      'SQLite, relative to root, without prefix and with the module name sqlite' => [
        'sqlite://localhost/test_database?module=sqlite',
        [
          'driver' => 'sqlite',
          'host' => 'localhost',
          'database' => $root . '/test_database',
          'namespace' => 'Drupal\sqlite\Driver\Database\sqlite',
          'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/',
        ],
        FALSE,
      ],
    ];
  }

  /**
   * Tests ::convertDbUrlToConnectionInfo() exception for invalid arguments.
   *
   * @dataProvider providerInvalidArgumentsUrlConversion
   */
  public function testGetInvalidArgumentExceptionInUrlConversion($url, $root, $expected_exception_message): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($expected_exception_message);
    Database::convertDbUrlToConnectionInfo($url, $root);
  }

  /**
   * Data provider for testGetInvalidArgumentExceptionInUrlConversion().
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - An invalid URL string.
   *   - Drupal root string.
   *   - The expected exception message.
   */
  public static function providerInvalidArgumentsUrlConversion() {
    return [
      ['foo', '', "Missing scheme in URL 'foo'"],
      ['foo', 'bar', "Missing scheme in URL 'foo'"],
      ['foo://', 'bar', "Can not convert 'foo://' to a database connection, the module providing the driver 'foo' is not specified"],
      ['foo://bar', 'baz', "Can not convert 'foo://bar' to a database connection, the module providing the driver 'foo' is not specified"],
      ['foo://bar:port', 'baz', "Can not convert 'foo://bar:port' to a database connection, the module providing the driver 'foo' is not specified"],
      ['foo/bar/baz', 'bar2', "Missing scheme in URL 'foo/bar/baz'"],
      ['foo://bar:baz@test1', 'test2', "Can not convert 'foo://bar:baz@test1' to a database connection, the module providing the driver 'foo' is not specified"],
    ];
  }

  /**
   * @covers ::getConnectionInfoAsUrl
   *
   * @dataProvider providerGetConnectionInfoAsUrl
   */
  public function testGetConnectionInfoAsUrl(array $info, $expected_url): void {
    Database::addConnectionInfo('default', 'default', $info);
    $url = Database::getConnectionInfoAsUrl();
    $this->assertEquals($expected_url, $url);
  }

  /**
   * Data provider for testGetConnectionInfoAsUrl().
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - An array mocking the database connection info. Possible keys are
   *     database, username, password, prefix, host, port, namespace and driver.
   *   - The expected URL after conversion.
   */
  public static function providerGetConnectionInfoAsUrl() {
    $info1 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => '',
      'host' => 'test_host',
      'port' => '3306',
      'driver' => 'mysql',
    ];
    $expected_url1 = 'mysql://test_user:test_pass@test_host:3306/test_database?module=mysql';

    $info2 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => 'pre',
      'host' => 'test_host',
      'port' => '3306',
      'driver' => 'mysql',
    ];
    $expected_url2 = 'mysql://test_user:test_pass@test_host:3306/test_database?module=mysql#pre';

    $info3 = [
      'database' => 'test_database',
      'driver' => 'sqlite',
    ];
    $expected_url3 = 'sqlite://localhost/test_database?module=sqlite';

    $info4 = [
      'database' => 'test_database',
      'driver' => 'sqlite',
      'prefix' => 'pre',
    ];
    $expected_url4 = 'sqlite://localhost/test_database?module=sqlite#pre';

    $info5 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => '',
      'host' => 'test_host',
      'port' => '3306',
      'driver' => 'DrivertestMysql',
      'namespace' => 'Drupal\\driver_test\\Driver\\Database\\DrivertestMysql',
      'autoload' => 'core/modules/system/tests/modules/driver_test/src/Driver/Database/DrivertestMysql/',
    ];
    $expected_url5 = 'DrivertestMysql://test_user:test_pass@test_host:3306/test_database?module=driver_test';

    $info6 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => 'pre',
      'host' => 'test_host',
      'port' => '3306',
      'driver' => 'DrivertestMysql',
      'namespace' => 'Drupal\\driver_test\\Driver\\Database\\DrivertestMysql',
      'autoload' => 'core/modules/system/tests/modules/driver_test/src/Driver/Database/DrivertestMysql/',
    ];
    $expected_url6 = 'DrivertestMysql://test_user:test_pass@test_host:3306/test_database?module=driver_test#pre';

    $info7 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => '',
      'host' => 'test_host',
      'port' => '5432',
      'driver' => 'DrivertestPgsql',
      'namespace' => 'Drupal\\driver_test\\Driver\\Database\\DrivertestPgsql',
      'autoload' => 'core/modules/system/tests/modules/driver_test/src/Driver/Database/DrivertestPgsql/',
    ];
    $expected_url7 = 'DrivertestPgsql://test_user:test_pass@test_host:5432/test_database?module=driver_test';

    $info8 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => 'pre',
      'host' => 'test_host',
      'port' => '5432',
      'driver' => 'DrivertestPgsql',
      'namespace' => 'Drupal\\driver_test\\Driver\\Database\\DrivertestPgsql',
      'autoload' => 'core/modules/system/tests/modules/driver_test/src/Driver/Database/DrivertestPgsql/',
    ];
    $expected_url8 = 'DrivertestPgsql://test_user:test_pass@test_host:5432/test_database?module=driver_test#pre';

    $info9 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => '',
      'host' => 'test_host',
      'port' => '3306',
      'driver' => 'DrivertestMysql',
      'namespace' => 'Drupal\\driver_test\\Driver\\Database\\DrivertestMysql',
      'autoload' => 'core/modules/system/tests/modules/driver_test/src/Driver/Database/DrivertestMysql/',
      'dependencies' => [
        'mysql' => [
          'namespace' => 'Drupal\mysql',
          'autoload' => 'core/modules/mysql/src/',
        ],
        'pgsql' => [
          'namespace' => 'Drupal\pgsql',
          'autoload' => 'core/modules/pgsql/src/',
        ],
      ],
    ];
    $expected_url9 = 'DrivertestMysql://test_user:test_pass@test_host:3306/test_database?module=driver_test';

    return [
      [$info1, $expected_url1],
      [$info2, $expected_url2],
      [$info3, $expected_url3],
      [$info4, $expected_url4],
      [$info5, $expected_url5],
      [$info6, $expected_url6],
      [$info7, $expected_url7],
      [$info8, $expected_url8],
      [$info9, $expected_url9],
    ];
  }

  /**
   * Tests ::getConnectionInfoAsUrl() exception for invalid arguments.
   *
   * @covers ::getConnectionInfoAsUrl
   *
   * @param array $connection_options
   *   The database connection information.
   * @param string $expected_exception_message
   *   The expected exception message.
   *
   * @dataProvider providerInvalidArgumentGetConnectionInfoAsUrl
   */
  public function testGetInvalidArgumentGetConnectionInfoAsUrl(array $connection_options, $expected_exception_message): void {
    Database::addConnectionInfo('default', 'default', $connection_options);
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($expected_exception_message);
    $url = Database::getConnectionInfoAsUrl();
  }

  /**
   * Data provider for testGetInvalidArgumentGetConnectionInfoAsUrl().
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - An array mocking the database connection info. Possible keys are
   *     database, username, password, prefix, host, port, namespace and driver.
   *   - The expected exception message.
   */
  public static function providerInvalidArgumentGetConnectionInfoAsUrl() {
    return [
      'Missing database key' => [
        [
          'driver' => 'sqlite',
          'host' => 'localhost',
          'namespace' => 'Drupal\sqlite\Driver\Database\sqlite',
        ],
        "As a minimum, the connection options array must contain at least the 'driver' and 'database' keys",
      ],
    ];
  }

  /**
   * @covers ::convertDbUrlToConnectionInfo
   */
  public function testDriverModuleDoesNotExist(): void {
    $url = 'foo_bar_mysql://test_user:test_pass@test_host:3306/test_database?module=foo_bar';
    $this->expectException(UnknownExtensionException::class);
    $this->expectExceptionMessage("The database_driver Drupal\\foo_bar\\Driver\\Database\\foo_bar_mysql does not exist.");
    Database::convertDbUrlToConnectionInfo($url, $this->root, TRUE);
  }

  /**
   * @covers ::convertDbUrlToConnectionInfo
   */
  public function testModuleDriverDoesNotExist(): void {
    $url = 'driver_test_mysql://test_user:test_pass@test_host:3306/test_database?module=driver_test';
    $this->expectException(UnknownExtensionException::class);
    $this->expectExceptionMessage("The database_driver Drupal\\driver_test\\Driver\\Database\\driver_test_mysql does not exist.");
    Database::convertDbUrlToConnectionInfo($url, $this->root, TRUE);
  }

}
