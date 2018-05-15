<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search;

use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Struct\Struct;

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
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var array
     */
    protected $ids;

    public function __construct(int $total, array $data, Criteria $criteria, ApplicationContext $context)
    {
        $this->total = $total;
        $this->ids = array_keys($data);
        $this->data = $data;
        $this->criteria = $criteria;
        $this->context = $context;
    }

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

    public function getContext(): ApplicationContext
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
}
