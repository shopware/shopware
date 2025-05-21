<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class QueryBuilder extends DBALQueryBuilder
{
    /**
     * @var array<string, string>
     */
    private array $states = [];

    /**
     * @var array<string, array{fromAlias: string, queryBuilder: self, joinCondition: string}>
     */
    private array $translationJoins = [];

    /**
     * @var array<string>
     */
    private array $selectParts = [];

    /**
     * @var array<string>
     */
    private array $oderByParts = [];

    private ?string $title = null;

    /**
     * SQL comment to be added to the query
     */
    private ?string $comment = null;

    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct($connection);
    }

    public function addState(string $state): void
    {
        $this->states[$state] = $state;
    }

    public function removeState(string $state): void
    {
        unset($this->states[$state]);
    }

    public function hasState(string $state): bool
    {
        return \in_array($state, $this->states, true);
    }

    /**
     * @return array<string, string>
     */
    public function getStates(): array
    {
        return $this->states;
    }

    public function addTranslationJoin(
        string $fromAlias,
        string $joinAlias,
        self $queryBuilder,
        string $joinCondition,
    ): void {
        $this->translationJoins[$joinAlias] = [
            'fromAlias' => $fromAlias,
            'queryBuilder' => $queryBuilder,
            'joinCondition' => $joinCondition,
        ];
    }

    public function getTranslationQueryBuilder(string $joinAlias): ?self
    {
        return $this->translationJoins[$joinAlias]['queryBuilder'] ?? null;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    /**
     * Sets an SQL comment to be prepended to the query.
     * This can be used for optimizer hints like MAX_EXECUTION_TIME.
     */
    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Gets the current SQL comment
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getSQL(): string
    {
        // Use a copy of this query builder to generate the SQL including the translation joins. This way calling this
        // getter does not have any side effects on the original instance.
        $query = clone $this;
        foreach ($this->translationJoins as $joinAlias => $translationJoin) {
            $query->leftJoin(
                $translationJoin['fromAlias'],
                '(' . $translationJoin['queryBuilder']->getSQL() . ')',
                $joinAlias,
                $translationJoin['joinCondition'],
            );
        }
        $sql = $query->getUnmodifiedSQL();

        // Add SQL comment/hint if set
        if ($this->getComment()) {
            // For SELECT queries, add the comment right after the SELECT keyword
            if (str_starts_with($sql, 'SELECT ')) {
                $sql = 'SELECT /*' . $this->comment . '*/ ' . substr($sql, 7);
            }
        }

        if ($this->getTitle()) {
            $sql = '# ' . $this->title . \PHP_EOL . $sql;
        }

        return $sql;
    }

    /**
     * Sets a query timeout for the current query.
     * This method will set the appropriate SQL comment/hint based on the database platform.
     */
    public function setQueryTimeout(int $timeout): self
    {
        $platform = $this->connection->getDatabasePlatform();
        $platformName = $platform::class;

        if (str_contains($platformName, 'MySQL')) {
            // MySQL 8 syntax - hint for a single query
            $this->setComment('+ MAX_EXECUTION_TIME(' . $timeout . ')');
        } elseif (str_contains($platformName, 'MariaDB')) {
            // MariaDB syntax - hint for a single query
            $this->setComment('+ MAX_STATEMENT_TIME(' . ($timeout / 1000) . ')');
        }

        return $this;
    }

    /**
     * @internal
     * {@inheritdoc}
     */
    public function select(string ...$expressions): self
    {
        $this->selectParts = $expressions;

        return parent::select(...$expressions);
    }

    /**
     * @internal
     * {@inheritdoc}
     */
    public function addSelect(string $expression, string ...$expressions): self
    {
        $this->selectParts = array_merge($this->selectParts, [$expression], $expressions);

        return parent::addSelect($expression, ...$expressions);
    }

    /**
     * @internal
     * {@inheritdoc}
     */
    public function orderBy(string $sort, ?string $order = null): self
    {
        $this->oderByParts = [$sort . ' ' . ($order ?? 'ASC')];

        return parent::orderBy($sort, $order);
    }

    /**
     * @internal
     * {@inheritdoc}
     */
    public function addOrderBy(string $sort, ?string $order = null): self
    {
        $this->oderByParts[] = $sort . ' ' . ($order ?? 'ASC');

        return parent::addOrderBy($sort, $order);
    }

    /**
     * This method is a hacky way to fix deprecations in the Doctrine DBAL QueryBuilder. It's usage is strongly discouraged.
     *
     * @internal
     *
     * @return array<string>
     */
    public function getSelectParts(): array
    {
        return $this->selectParts;
    }

    /**
     * This method is a hacky way to fix deprecations in the Doctrine DBAL QueryBuilder. It's usage is strongly discouraged.
     *
     * @return array<string>
     *
     *@internal
     */
    public function getOrderByParts(): array
    {
        return $this->oderByParts;
    }

    /**
     * A helper function allowing to get the SQL without applying translation joins. This is necessary for preventing
     * infinite recursion in {@link self::getSQL()}.
     */
    private function getUnmodifiedSQL(): string
    {
        return parent::getSQL();
    }
}
