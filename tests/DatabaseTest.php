<?php

namespace Finesse\MiniDB\Tests;

use Finesse\MicroDB\Connection;
use Finesse\MicroDB\Exceptions\InvalidArgumentException as ConnectionInvalidArgumentException;
use Finesse\MicroDB\Exceptions\PDOException as ConnectionPDOException;
use Finesse\MiniDB\Database;
use Finesse\MiniDB\Exceptions\DatabaseException;
use Finesse\MiniDB\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Grammars\CommonGrammar;
use Finesse\QueryScribe\Grammars\MySQLGrammar;

/**
 * Tests the Database class
 *
 * @author Surgie
 */
class DatabaseTest extends TestCase
{
    /**
     * Tests the `create` method
     */
    public function testCreate()
    {
        $database = Database::create([
            'dns' => 'sqlite::memory:'
        ]);
        $this->assertInstanceOf(Connection::class, $database->getConnection());
        $this->assertInstanceOf(CommonGrammar::class, $database->getGrammar());
        $this->assertEquals('', $database->getTablePrefix());

        $database = Database::create([
            'driver' => 'MySQL',
            'dns' => 'sqlite::memory:',
            'username' => null,
            'password' => null,
            'options' => null,
            'prefix' => 'test_'
        ]);
        $this->assertInstanceOf(Connection::class, $database->getConnection());
        $this->assertInstanceOf(MySQLGrammar::class, $database->getGrammar());
        $this->assertEquals('test_', $database->getTablePrefix());

        $this->assertException(DatabaseException::class, function () {
            Database::create([
                'dns' => 'foo:bar'
            ]);
        });
    }

    /**
     * Tests the plain database query methods
     */
    public function testRawQueries()
    {
        $connection = Connection::create('sqlite::memory:');
        $connection->statement('CREATE TABLE test(id INTEGER PRIMARY KEY ASC, name TEXT, value NUMERIC)');
        $connection->insert(
            'INSERT INTO test (name, value) VALUES (?, ?), (?, ?), (?, ?)',
            ['Banana', 123.4, 'Apple', -10, 'Pen', 0]
        );
        $database = new Database($connection);

        // Select
        $this->assertEquals([
            ['id' => 1, 'name' => 'Banana', 'value' => 123.4],
            ['id' => 3, 'name' => 'Pen', 'value' => 0]
        ], $database->select('SELECT * FROM test WHERE value >= ? ORDER BY id', [0]));
        $this->assertException(DatabaseException::class, function () use ($database) {
            $database->select('WRONG SQL');
        });

        // Select first
        $this->assertEquals(
            ['id' => 1, 'name' => 'Banana', 'value' => 123.4],
            $database->selectFirst('SELECT * FROM test ORDER BY id')
        );
        $this->assertNull($database->selectFirst('SELECT * FROM test WHERE name = ?', ['Orange']));
        $this->assertException(DatabaseException::class, function () use ($database) {
            $database->selectFirst('WRONG SQL');
        });

        // Insert and get the count
        $this->assertEquals(2, $database->insert(
            'INSERT INTO test (name, value) VALUES (?, ?), (?, ?)',
            ['Orange', 314, 'Pillow', 219]
        ));
        $this->assertEquals(5, $connection->selectFirst('SELECT COUNT(*) AS count FROM test')['count']);
        $this->assertException(DatabaseException::class, function () use ($database) {
            $database->insert('WRONG SQL');
        });

        // Insert and get the last id
        $this->assertEquals(6, $database->insertGetId('INSERT INTO test (name, value) VALUES (?, ?)', ['Mug', -1]));
        $this->assertEquals(6, $connection->selectFirst('SELECT COUNT(*) AS count FROM test')['count']);
        $this->assertException(DatabaseException::class, function () use ($database) {
            $database->insertGetId('WRONG SQL');
        });

        // Update
        $this->assertEquals(3, $database->update('UPDATE test SET name = name || ? WHERE value > ?', ['!', 100]));
        $this->assertEquals([
            ['name' =>'Banana!'],
            ['name' =>'Orange!'],
            ['name' =>'Pillow!']
        ], $connection->select('SELECT name FROM test WHERE value > ? ORDER BY id', [100]));
        $this->assertException(DatabaseException::class, function () use ($database) {
            $database->update('WRONG SQL');
        });

        // Delete
        $this->assertEquals(2, $database->delete('DELETE FROM test WHERE value < ?', [0]));
        $this->assertEquals([
            ['name' =>'Banana!'],
            ['name' =>'Pen'],
            ['name' =>'Orange!'],
            ['name' =>'Pillow!']
        ], $connection->select('SELECT name FROM test ORDER BY id'));
        $this->assertException(DatabaseException::class, function () use ($database) {
            $database->delete('WRONG SQL');
        });

        // Statement
        $database->statement('DROP TABLE test');
        $this->assertEmpty($connection->select(
            'SELECT name FROM sqlite_master WHERE type = ? AND name = ?',
            ['table', 'test'])
        );
        $this->assertException(DatabaseException::class, function () use ($database) {
            $database->statement('WRONG SQL');
        });
    }

    /**
     * Tests more error cases
     */
    public function testErrors()
    {
        $database = Database::create(['dns' => 'sqlite::memory:']);

        // Wrapping Connection PDOException
        $this->assertException(DatabaseException::class, function () use ($database) {
            $database->select('WRONG SQL', ['foo', 'bar', true, 123]);
        }, function (DatabaseException $exception) {
            $this->assertStringEndsWith(
                '; SQL query: (WRONG SQL); bound values: ["foo", "bar", true, 123]',
                $exception->getMessage()
            );
            $this->assertEquals('WRONG SQL', $exception->getQuery());
            $this->assertEquals(['foo', 'bar', true, 123], $exception->getValues());
            $this->assertInstanceOf(ConnectionPDOException::class, $exception->getPrevious());
        });

        // Wrapping Connection InvalidArgumentException
        $this->assertException(InvalidArgumentException::class, function () use ($database) {
            $database->select('SELECT * FROM sqlite_master WHERE name IN (?)', [['Anny', 'Bob']]);
        }, function (InvalidArgumentException $exception) {
            $this->assertInstanceOf(ConnectionInvalidArgumentException::class, $exception->getPrevious());
        });

        // Wrapping any other exception
        $connection = new class extends Connection {
            public function __construct() {}
            public function select(string $query, array $values = []): array
            {
                throw new \Exception('test');
            }
        };
        $database = new Database($connection);
        $this->assertException(\Exception::class, function () use ($database) {
            $database->select('');
        }, function (\Exception $exception) {
            $this->assertEquals('test', $exception->getMessage());
        });
    }
}