<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Search;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('merchant-services')]
class ExtensionCriteria extends Struct
{
    final public const ORDER_SEQUENCE_ASC = 'asc';
    final public const ORDER_SEQUENCE_DESC = 'desc';

    private int $limit = 10;

    private int $offset = 0;

    private ?string $search = null;

    private ?string $orderBy = null;

    private string $orderSequence = self::ORDER_SEQUENCE_ASC;

    /**
     * @var FilterStruct[]
     */
    private array $filter = [];

    public static function fromArray(array $parameter): ExtensionCriteria
    {
        $criteria = new ExtensionCriteria();

        if (isset($parameter['limit'])) {
            $criteria->setLimit((int) $parameter['limit']);
        }

        if (isset($parameter['page'])) {
            $criteria->setOffset(((int) $parameter['page'] - 1) * $criteria->getLimit());
        }

        if (isset($parameter['term'])) {
            $criteria->setSearch($parameter['term']);
        }

        $sorting = $parameter['sort'][0] ?? null;
        if ($sorting !== null) {
            $criteria->setOrderBy($sorting['field']);
            $criteria->setOrderSequence($sorting['order']);
        }

        if (isset($parameter['filter'])) {
            foreach ($parameter['filter'] as $filter) {
                $criteria->addFilter($filter);
            }
        }

        return $criteria;
    }

    /**
     * @return array<string, int|string>
     */
    public function getQueryParameter(): array
    {
        $options = [
            'limit' => $this->getLimit(),
            'offset' => $this->getOffset(),
        ];

        if ($this->search !== null) {
            $options['search'] = $this->search;
        }

        if ($this->orderBy !== null) {
            $options['orderBy'] = $this->orderBy;
            $options['orderSequence'] = $this->orderSequence;
        }

        foreach ($this->getFilter() as $filter) {
            $options = [...$options, ...$filter->getQueryParameter()];
        }

        return $options;
    }

    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setOffset(int $offset): void
    {
        $this->offset = $offset;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setSearch(string $search): void
    {
        $this->search = $search;
    }

    public function getSearch(): ?string
    {
        return $this->search;
    }

    public function setOrderBy(string $orderBy): void
    {
        $this->orderBy = $orderBy;
    }

    public function getOrderBy(): ?string
    {
        return $this->orderBy;
    }

    public function setOrderSequence(string $orderSequence): void
    {
        if (mb_strtolower($orderSequence) === self::ORDER_SEQUENCE_DESC) {
            $this->orderSequence = self::ORDER_SEQUENCE_DESC;

            return;
        }

        $this->orderSequence = self::ORDER_SEQUENCE_ASC;
    }

    public function getOrderSequence(): string
    {
        return $this->orderSequence;
    }

    public function addFilter(array $filter): void
    {
        $this->filter[] = FilterStruct::fromArray($filter);
    }

    /**
     * @return FilterStruct[]
     */
    public function getFilter(): array
    {
        return $this->filter;
    }
}
