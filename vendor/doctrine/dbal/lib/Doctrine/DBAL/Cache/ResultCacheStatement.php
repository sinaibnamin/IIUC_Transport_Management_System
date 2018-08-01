<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Cache;

use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\FetchMode;
use function array_merge;
use function array_values;
use function reset;

/**
 * Cache statement for SQL results.
 *
 * A result is saved in multiple cache keys, there is the originally specified
 * cache key which is just pointing to result rows by key. The following things
 * have to be ensured:
 *
 * 1. lifetime of the original key has to be longer than that of all the individual rows keys
 * 2. if any one row key is missing the query has to be re-executed.
 *
 * Also you have to realize that the cache will load the whole result into memory at once to ensure 2.
 * This means that the memory usage for cached results might increase by using this feature.
 */
class ResultCacheStatement implements \IteratorAggregate, ResultStatement
{
    /**
     * @var \Doctrine\Common\Cache\Cache
     */
    private $resultCache;

    /**
     *
     * @var string
     */
    private $cacheKey;

    /**
     * @var string
     */
    private $realKey;

    /**
     * @var int
     */
    private $lifetime;

    /**
     * @var \Doctrine\DBAL\Driver\Statement
     */
    private $statement;

    /**
     * Did we reach the end of the statement?
     *
     * @var bool
     */
    private $emptied = false;

    /**
     * @var array
     */
    private $data;

    /**
     * @var int
     */
    private $defaultFetchMode = FetchMode::MIXED;

    /**
     * @param \Doctrine\DBAL\Driver\Statement $stmt
     * @param \Doctrine\Common\Cache\Cache $resultCache
     * @param string $cacheKey
     * @param string $realKey
     * @param int $lifetime
     */
    public function __construct(Statement $stmt, Cache $resultCache, $cacheKey, $realKey, $lifetime)
    {
        $this->statement = $stmt;
        $this->resultCache = $resultCache;
        $this->cacheKey = $cacheKey;
        $this->realKey = $realKey;
        $this->lifetime = $lifetime;
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        $this->statement->closeCursor();
        if ($this->emptied && $this->data !== null) {
            $data = $this->resultCache->fetch($this->cacheKey);
            if (!$data) {
                $data = [];
            }
            $data[$this->realKey] = $this->data;

            $this->resultCache->save($this->cacheKey, $data, $this->lifetime);
            unset($this->data);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return $this->statement->columnCount();
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $this->defaultFetchMode = $fetchMode;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        $data = $this->fetchAll();

        return new \ArrayIterator($data);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        $rows = [];
        while ($row = $this->fetch($fetchMode)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null, $cursorOrientation = \PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        if ($this->data === null) {
            $this->data = [];
        }

        $row = $this->statement->fetch(FetchMode::ASSOCIATIVE);

        if ($row) {
            $this->data[] = $row;

            $fetchMode = $fetchMode ?: $this->defaultFetchMode;

            if ($fetchMode === FetchMode::ASSOCIATIVE) {
                return $row;
            }

            if ($fetchMode === FetchMode::NUMERIC) {
                return array_values($row);
            }

            if ($fetchMode === FetchMode::MIXED) {
                return array_merge($row, array_values($row));
            }

            if ($fetchMode === FetchMode::COLUMN) {
                return reset($row);
            }

            throw new \InvalidArgumentException('Invalid fetch-style given for caching result.');
        }

        $this->emptied = true;

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        $row = $this->fetch(FetchMode::NUMERIC);

        // TODO: verify that return false is the correct behavior
        return $row[$columnIndex] ?? false;
    }

    /**
     * Returns the number of rows affected by the last DELETE, INSERT, or UPDATE statement
     * executed by the corresponding object.
     *
     * If the last SQL statement executed by the associated Statement object was a SELECT statement,
     * some databases may return the number of rows returned by that statement. However,
     * this behaviour is not guaranteed for all databases and should not be
     * relied on for portable applications.
     *
     * @return int The number of rows.
     */
    public function rowCount()
    {
        return $this->statement->rowCount();
    }
}
