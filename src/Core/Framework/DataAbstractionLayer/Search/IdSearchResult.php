<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\StateAwareTrait;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @final
 */
#[Package('core')]
class IdSearchResult extends Struct
{
    use StateAwareTrait;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected $data;

    /**
     * @var list<string>|list<array<string, string>>
     */
    protected $ids;

    /**
     * @param array<array<string, mixed>> $data
     */
    public function __construct(
        private readonly int $total,
        array $data,
        private readonly Criteria $criteria,
        private readonly Context $context
    ) {
        $this->ids = array_column($data, 'primaryKey');

        $this->data = array_map(fn ($row) => $row['data'], $data);
    }

    public function firstId(): ?string
    {
        if (empty($this->ids)) {
            return null;
        }

        $id = $this->ids[0];

        if (!\is_string($id)) {
            throw new \RuntimeException('Calling IdSearchResult::firstId() is not supported for mapping entities.');
        }

        return $id;
    }

    /**
     * @return list<string>|list<array<string, string>>
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

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDataOfId(string $id): array
    {
        if (!\array_key_exists($id, $this->data)) {
            return [];
        }

        return $this->data[$id];
    }

    public function getDataFieldOfId(string $id, string $field): mixed
    {
        $data = $this->getDataOfId($id);

        if (\array_key_exists($field, $data)) {
            return $data[$field];
        }

        return null;
    }

    public function getScore(string $id): float
    {
        $score = $this->getDataFieldOfId($id, '_score');

        if ($score === null) {
            throw new \RuntimeException('No score available for id ' . $id);
        }

        return (float) $score;
    }

    /**
     * @param string|array<string, string> $primaryKey
     */
    public function has(string|array $primaryKey): bool
    {
        if (!\is_array($primaryKey)) {
            return \in_array($primaryKey, $this->ids, true);
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
