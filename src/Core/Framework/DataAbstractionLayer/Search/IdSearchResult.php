<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;

class IdSearchResult extends Struct
{
    /**
     * @var array[]
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
        $this->ids = array_column($data, 'primaryKey');

        $this->data = array_map(function ($row) {
            return $row['data'];
        }, $data);

        $this->criteria = $criteria;
        $this->context = $context;
    }

    public function firstId(): ?string
    {
        if (empty($this->ids)) {
            return null;
        }

        return $this->ids[0];
    }

    /**
     * @return array[]|string[]
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

    /**
     * @param string|array $primaryKey
     */
    public function has($primaryKey): bool
    {
        if (!is_array($primaryKey)) {
            return in_array($primaryKey, $this->ids, true);
        }

        foreach ($this->ids as $id) {
            if ($id === $primaryKey) {
                return true;
            }
        }

        return false;
    }

    public function getApiAlias(): string
    {
        return 'dal_id_search_result';
    }
}
