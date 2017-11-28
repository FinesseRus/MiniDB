<?php

namespace Finesse\MiniDB;

use Finesse\MiniDB\Exceptions\DatabaseException;
use Finesse\MiniDB\Exceptions\IncorrectQueryException;
use Finesse\MiniDB\Exceptions\InvalidArgumentException;
use Finesse\MiniDB\Exceptions\InvalidReturnValueException;
use Finesse\MiniDB\Parts\InsertTrait;
use Finesse\MiniDB\Parts\RawHelpersTrait;
use Finesse\MiniDB\Parts\SelectTrait;
use Finesse\QueryScribe\Query as BaseQuery;
use Finesse\QueryScribe\StatementInterface;

/**
 * Query builder. Builds SQL queries and performs them on a database.
 *
 * All the methods throw Finesse\MiniDB\Exceptions\ExceptionInterface.
 *
 * {@inheritDoc}
 *
 * @author Surgie
 */
class Query extends BaseQuery
{
    use SelectTrait, InsertTrait, RawHelpersTrait;

    /**
     * @var Database Database on which the query should be performed
     */
    protected $database;

    /**
     * @param Database $database Database on which the query should be performed
     */
    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * {@inheritDoc}
     */
    public function makeEmptyCopy(): BaseQuery
    {
        return new static($this->database);
    }

    /**
     * Updates the query target rows. Doesn't modify itself.
     *
     * @param mixed[]|\Closure[]|self[]|StatementInterface[] $values Fields to update. The indexes are the columns
     *     names, the values are the values.
     * @return int The number of updated rows
     * @throws DatabaseException
     * @throws IncorrectQueryException
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function update(array $values): int
    {
        try {
            $query = (clone $this)->addUpdate($values);
            $query = $this->database->getTablePrefixer()->process($query);
            $compiled = $this->database->getGrammar()->compileUpdate($query);
            return $this->database->update($compiled->getSQL(), $compiled->getBindings());
        } catch (\Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    /**
     * Deletes the query target rows. Doesn't modify itself.
     *
     * @return int The number of deleted rows
     * @throws DatabaseException
     * @throws IncorrectQueryException
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function delete(): int
    {
        try {
            $query = (clone $this)->setDelete();
            $query = $this->database->getTablePrefixer()->process($query);
            $compiled = $this->database->getGrammar()->compileDelete($query);
            return $this->database->delete($compiled->getSQL(), $compiled->getBindings());
        } catch (\Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function handleException(\Throwable $exception)
    {
        try {
            return parent::handleException($exception);
        } catch (\Throwable $exception) {
            throw Helpers::wrapException($exception);
        }
    }
}
