<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

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

    private ?string $title = null;

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
     * @deprecated tag:v6.7.0 - reason:return-type-change - return type will be changed to string
     *
     * @return string
     */
    public function getSQL()
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

        if ($this->title) {
            $sql = '-- ' . self::sanitizeSqlComment($this->title) . \PHP_EOL . $sql;
        }

        return $sql;
    }

    /**
     * SQL has no built-in escaping for comments. Line breaks terminate a `--` comment, and other control characters
     * can be interpreted differently by SQL parsers. Replacing Unicode control characters prevents titles from
     * changing the query grammar.
     */
    private static function sanitizeSqlComment(string $comment): string
    {
        // https://www.php.net/manual/en/regexp.reference.unicode.php
        return preg_replace('/\p{Cc}/u', ' ', $comment) ?? '';
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
