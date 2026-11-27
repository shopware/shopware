<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Query\Exception\UnknownAlias;
use Doctrine\DBAL\Result;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class PrimaryKeyScanQueryBuilder extends QueryBuilder
{
    private bool $useIndex = true;

    private readonly string $table;

    private readonly string $index;

    public function __construct(Connection $connection, string $table, string $index)
    {
        parent::__construct($connection);
        $this->table = EntityDefinitionQueryHelper::escape($table);
        $this->index = EntityDefinitionQueryHelper::escape($index);
    }

    public function getSQL(): string
    {
        if ($this->useIndex) {
            try {
                return (clone $this)->renderFrom($this->table . ' FORCE INDEX (' . $this->index . ')');
            } catch (UnknownAlias) {
                // DBAL keys joins by the original FROM alias. Let the optimizer plan customized joins.
            }
        }

        return (clone $this)->renderFrom($this->table);
    }

    public function executeQuery(): Result
    {
        try {
            return parent::executeQuery();
        } catch (DriverException $e) {
            // MySQL and MariaDB report 1176 for an absent or optimizer-invisible index.
            if (!$this->useIndex || $e->getCode() !== 1176) {
                throw $e;
            }

            $this->useIndex = false;

            return parent::executeQuery();
        }
    }

    private function renderFrom(string $table): string
    {
        $this->from($table);

        return parent::getSQL();
    }
}
