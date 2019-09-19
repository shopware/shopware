<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\Struct\Struct;

class Bucket extends Struct
{
    /**
     * @var string
     */
    protected $key;

    /**
     * @var int
     */
    protected $count;

    /**
     * @var AggregationResult|null
     */
    protected $result;

    public function __construct(?string $key, int $count, ?AggregationResult $result)
    {
        $this->key = $key;
        $this->count = $count;
        $this->result = $result;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getResult(): ?AggregationResult
    {
        return $this->result;
    }

    public function jsonSerialize(): array
    {
        $data = get_object_vars($this);

        if ($data['result'] === null) {
            unset($data['result']);

            return $data;
        }

        $data[$this->result->getName()] = $data['result'];
        unset($data['result']);

        return $data;
    }

    public function incrementCount(int $count): void
    {
        $this->count += $count;
    }
}
