<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;

class IdSearchResult extends Struct
{
    /**
     * @var string[]
     */
    protected $data;

    /**
     * @var int
     */
    protected $total;

    /**
     * @var Criteria
     */
    protected $criteria;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var array
     */
    protected $ids;

    public function __construct(int $total, array $data, Criteria $criteria, Context $context)
    {
        $this->total = $total;
        $this->setData($data);
        $this->criteria = $criteria;
        $this->context = $context;
    }

    /**
     * @return string[]
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getDataOfId(string $id): array
    {
        if (!array_key_exists($id, $this->data)) {
            return [];
        }

        return $this->data[$id];
    }

    public function getDataFieldOfId(string $id, string $field)
    {
        $data = $this->getDataOfId($id);

        if (array_key_exists($field, $data)) {
            return $data[$field];
        }

        return null;
    }

    public function setData(array $data): void
    {
        $this->ids = array_keys($data);
        $this->data = $data;
    }
}
