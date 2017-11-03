<?php

namespace Finesse\MiniDB\QueryParts;

use Finesse\MiniDB\Exceptions\DatabaseException;
use Finesse\MiniDB\Exceptions\IncorrectQueryException;
use Finesse\MiniDB\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\StatementInterface;

/**
 * Contains methods for performing select queries with Query.
 *
 * @author Surgie
 */
trait SelectTrait
{
    /**
     * Performs a select query and returns the selected rows.
     *
     * @return array[] Array of the result rows. Result row is an array indexed by columns.
     * @throws DatabaseException
     * @throws IncorrectQueryException
     */
    public function get(): array
    {
        return $this->performQuery(function () {
            $compiled = $this->database->getGrammar()->compileSelect($this);
            return $this->database->select($compiled->getSQL(), $compiled->getBindings());
        });
    }

    /**
     * Performs a select query and returns the first selected row.
     *
     * @return array|null An array indexed by columns. Null if nothing is found.
     * @throws DatabaseException
     * @throws IncorrectQueryException
     */
    public function first()
    {
        return $this->performQuery(function () {
            $this->limit(1);
            $compiled = $this->database->getGrammar()->compileSelect($this);
            return $this->database->selectFirst($compiled->getSQL(), $compiled->getBindings());
        });
    }

    /**
     * Gets the count of the target rows.
     *
     * @param string|\Closure|self|StatementInterface $column Column to count
     * @return int
     * @throws DatabaseException
     * @throws IncorrectQueryException
     * @throws InvalidArgumentException
     */
    public function count($column = '*'): int
    {
        return $this->performQuery(function () use ($column) {
            $this->select = [];
            $this->addCount($column, 'aggregate')->offset(null)->limit(null);
            $compiled = $this->database->getGrammar()->compileSelect($this);
            return $this->database->selectFirst($compiled->getSQL(), $compiled->getBindings())['aggregate'];
        });
    }

    /**
     * Gets the average value of the target rows.
     *
     * @param string|\Closure|self|StatementInterface $column Column to get average
     * @return float|null Null is returned when no target row has a value
     * @throws DatabaseException
     * @throws IncorrectQueryException
     * @throws InvalidArgumentException
     */
    public function avg($column)
    {
        return $this->performQuery(function () use ($column) {
            $this->select = [];
            $this->addAvg($column, 'aggregate')->offset(null)->limit(null);
            $compiled = $this->database->getGrammar()->compileSelect($this);
            return $this->database->selectFirst($compiled->getSQL(), $compiled->getBindings())['aggregate'];
        });
    }

    /**
     * Gets the sum of the target rows.
     *
     * @param string|\Closure|self|StatementInterface $column Column to get sum
     * @return float|null Null is returned when no target row has a value
     * @throws DatabaseException
     * @throws IncorrectQueryException
     * @throws InvalidArgumentException
     */
    public function sum($column)
    {
        return $this->performQuery(function () use ($column) {
            $this->select = [];
            $this->addSum($column, 'aggregate')->offset(null)->limit(null);
            $compiled = $this->database->getGrammar()->compileSelect($this);
            return $this->database->selectFirst($compiled->getSQL(), $compiled->getBindings())['aggregate'];
        });
    }

    /**
     * Gets the minimum value of the target rows.
     *
     * @param string|\Closure|self|StatementInterface $column Column to get minimum
     * @return float|null Null is returned when no target row has a value
     * @throws DatabaseException
     * @throws IncorrectQueryException
     * @throws InvalidArgumentException
     */
    public function min($column)
    {
        return $this->performQuery(function () use ($column) {
            $this->select = [];
            $this->addMin($column, 'aggregate')->offset(null)->limit(null);
            $compiled = $this->database->getGrammar()->compileSelect($this);
            return $this->database->selectFirst($compiled->getSQL(), $compiled->getBindings())['aggregate'];
        });
    }

    /**
     * Gets the maximum value of the target rows.
     *
     * @param string|\Closure|self|StatementInterface $column Column to get maximum
     * @return float|null Null is returned when no target row has a value
     * @throws DatabaseException
     * @throws IncorrectQueryException
     * @throws InvalidArgumentException
     */
    public function max($column)
    {
        return $this->performQuery(function () use ($column) {
            $this->select = [];
            $this->addMax($column, 'aggregate')->offset(null)->limit(null);
            $compiled = $this->database->getGrammar()->compileSelect($this);
            return $this->database->selectFirst($compiled->getSQL(), $compiled->getBindings())['aggregate'];
        });
    }

    /**
     * Walks large amount of rows calling a callback on small portions of rows.
     *
     * @param int $size Number of rows per callback call
     * @param callable $callback The callback. Receives an array of rows as the first argument.
     * @throws DatabaseException
     * @throws IncorrectQueryException
     * @throws InvalidArgumentException
     */
    public function chunk(int $size, callable $callback)
    {
        if ($size <= 0) {
            throw new InvalidArgumentException('Chunk size must be greater than zero');
        }

        for ($offset = 0;; $offset += $size) {
            $rows = $this->offset($offset)->limit($size)->get();
            if (empty($rows)) {
                break;
            }

            $callback($rows);

            if (count($rows) < $size) {
                break;
            }
        }
    }
}