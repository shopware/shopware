<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('framework')]
class LastIdQuery implements IterableQuery
{
    private int|string|null $lastId = null;

    public function __construct(private readonly QueryBuilder $query)
    {
    }

    public function fetch(): array
    {
        $data = $this->query->executeQuery()->fetchAllKeyValue();
        if ($data === []) {
            return [];
        }

        $lastId = array_key_last($data);
        $binary = $this->query->getParameterType('lastId') === ParameterType::BINARY;
        $this->lastId = $binary ? Uuid::fromBytesToHex((string) $lastId) : (int) $lastId;

        $this->query->setParameter('lastId', $binary ? (string) $lastId : $this->lastId, $binary ? ParameterType::BINARY : ParameterType::INTEGER);

        return $data;
    }

    public function fetchCount(): int
    {
        $query = clone $this->query;

        // get first column for distinct selection
        $select = $query->getSelectParts();

        $query->resetOrderBy();
        $query->select('COUNT(DISTINCT ' . array_shift($select) . ')');

        return (int) $query->executeQuery()->fetchOne();
    }

    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    public function getOffset(): array
    {
        return ['offset' => $this->lastId];
    }
}
