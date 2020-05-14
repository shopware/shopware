<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Message;

class RecalculatePricesForCurrencyMessage
{
    /**
     * @var string
     */
    private $table;

    /**
     * @var array
     */
    private $fields;

    /**
     * @var array
     */
    private $ids;

    /**
     * @var float
     */
    private $multiplyWith;

    public function __construct(string $table, array $fields, array $ids, float $multiplyWith)
    {
        $this->table = $table;
        $this->fields = $fields;
        $this->ids = $ids;
        $this->multiplyWith = $multiplyWith;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function getMultiplyWith(): float
    {
        return $this->multiplyWith;
    }
}
