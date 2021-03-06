<?php

namespace Finesse\MiniDB\Tests;

use Finesse\MiniDB\Database;
use Finesse\MiniDB\Exceptions\DatabaseException;
use Finesse\MiniDB\Exceptions\IncorrectQueryException;
use Finesse\MiniDB\Exceptions\InvalidArgumentException;
use Finesse\MiniDB\Query;
use Finesse\QueryScribe\Exceptions\InvalidArgumentException as QueryScribeInvalidArgumentException;
use Finesse\QueryScribe\Exceptions\InvalidQueryException as QueryScribeInvalidQueryException;

/**
 * Tests the Query class
 *
 * @author Surgie
 */
class QueryTest extends TestCase
{
    /**
     * Tests the `update` method
     */
    public function testUpdate()
    {
        $database = Database::create(['driver' => 'sqlite', 'dsn' => 'sqlite::memory:', 'prefix' => 'pre_']);
        $database->statement('CREATE TABLE '.$database->addTablePrefix('items')
            . '(id INTEGER PRIMARY KEY ASC, name TEXT, value NUMERIC)');
        $database->insert(
            'INSERT INTO '.$database->addTablePrefix('items').' (name, value) VALUES (?, ?), (?, ?), (?, ?), (?, ?)',
            ['Banana', 123.4, 'Apple', -10, 'Pen', null, 'Bottle', 0]
        );

        $this->assertEquals(1, $database->table('items')->whereNull('value')->update(['value' => 12]));
        $this->assertEquals(
            ['id' => '3', 'name' => 'Pen', 'value' => 12],
            $database->selectFirst('SELECT * FROM pre_items WHERE id = ?', [3])
        );

        $this->assertEquals(4, $database->table('items')->update(['name' => 'Lol', 'value' => null]));
        $rows = $database->select('SELECT * FROM pre_items');
        $this->assertCount(4, $rows);
        foreach ($rows as $row) {
            $this->assertEquals('Lol', $row['name']);
            $this->assertNull($row['value']);
        }

        $this->assertException(IncorrectQueryException::class, function () use ($database) {
            (new Query($database))->update(['foo' => 'bar']);
        });
    }

    /**
     * Tests the `delete` method
     */
    public function testDelete()
    {
        $database = Database::create(['driver' => 'sqlite', 'dsn' => 'sqlite::memory:', 'prefix' => 'pre_']);
        $database->statement('CREATE TABLE '.$database->addTablePrefix('items')
            . '(id INTEGER PRIMARY KEY ASC, name TEXT, value NUMERIC)');
        $database->insert(
            'INSERT INTO '.$database->addTablePrefix('items').' (name, value) VALUES (?, ?), (?, ?), (?, ?), (?, ?)',
            ['Banana', 123.4, 'Apple', -10, 'Pen', null, 'Bottle', 0]
        );

        $this->assertEquals(1, $database->table('items')->where('value', '<', 0)->delete());
        $this->assertEquals([
            ['id' => 1],
            ['id' => 3],
            ['id' => 4]
        ], $database->select('SELECT id FROM pre_items ORDER BY id'));

        $this->assertEquals(3, $database->table('items')->delete());
        $this->assertEmpty($database->select('SELECT * FROM pre_items'));

        $this->assertException(IncorrectQueryException::class, function () use ($database) {
            (new Query($database))->delete();
        });
    }

    /**
     * Tests the `addTablesToColumnNames` method
     */
    public function testAddTablesToColumnNames()
    {
        $database = Database::create(['driver' => 'sqlite', 'dsn' => 'sqlite::memory:', 'prefix' => 'pre_']);
        $database->statement('CREATE TABLE '.$database->addTablePrefix('items')
            . '(id INTEGER PRIMARY KEY ASC, name TEXT)');
        $database->insert(
            'INSERT INTO '.$database->addTablePrefix('items').' (name) VALUES (?), (?), (?), (?)',
            ['Mars', 'Venus', 'Earth', 'Pluto']
        );

        $query = $database->table('items')->addSelect('name');
        $explicitQuery = $query->addTablesToColumnNames();
        $this->assertEquals(
            $database->table('items')->addSelect('name'),
            $query
        );
        $this->assertEquals(
            $database->table('items')->addSelect('items.name'),
            $explicitQuery
        );

        $this->assertEquals([
            ['name' => 'Mars'],
            ['name' => 'Venus']
        ], $explicitQuery->limit(2)->get());
    }

    /**
     * Tests more error cases
     */
    public function testErrors()
    {
        $database = Database::create(['driver' => 'sqlite', 'dsn' => 'sqlite::memory:', 'prefix' => 'pre_']);

        $this->assertException(IncorrectQueryException::class, function () use ($database) {
            $database->table('animals')->offset(10)->get();
        }, function (IncorrectQueryException $exception) {
            $this->assertInstanceOf(QueryScribeInvalidQueryException::class, $exception->getPrevious());
        });

        $this->assertException(DatabaseException::class, function () use ($database) {
            $database->table('animals')->get();
        });

        $this->assertException(InvalidArgumentException::class, function () use ($database) {
            $database->table('animals')->where(new \stdClass);
        }, function (InvalidArgumentException $exception) {
            $this->assertInstanceOf(QueryScribeInvalidArgumentException::class, $exception->getPrevious());
        });

        // Any other unexpected error
        $query = new class ($database) extends Query {
            public function throwUnexpectedException() {
                return $this->handleException(new \TypeError('Hello'));
            }
        };
        $this->assertException(\TypeError::class, function () use ($query) {
            $query->throwUnexpectedException();
        }, function (\TypeError $exception) {
            $this->assertEquals('Hello', $exception->getMessage());
        });
    }
}
