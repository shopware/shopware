<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeWidening;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('framework')]
class LastIdQuery implements IterableQuery
{
    private int|string|null $lastId = null;

    public function __construct(private readonly QueryBuilder $query, private ?string $initialUuidPredicate = null)
    {
    }

    public function fetch(): array
    {
        $data = $this->query->executeQuery()->fetchAllKeyValue();
        if ($data === []) {
            return [];
        }

        $lastId = array_key_last($data);
        $binary = $this->initialUuidPredicate !== null || $this->query->getParameterType('lastId') === ParameterType::BINARY;
        if ($this->initialUuidPredicate !== null) {
            $this->query->andWhere($this->initialUuidPredicate);
            $this->initialUuidPredicate = null;
        }

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

    #[ReturnTypeWidening(version: 'v6.8.0', newType: 'array{offset: int|string|null}')]
    public function getOffset(): array
    {
        return ['offset' => $this->lastId];
    }
}
