<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Search;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('merchant-services')]
class EqualsFilterStruct extends FilterStruct
{
    /**
     * @var string
     */
    protected $field;

    /**
     * @var string
     */
    protected $value;

    public static function fromArray(array $data): FilterStruct
    {
        $filter = new EqualsFilterStruct();
        $filter->assign($data);

        return $filter;
    }

    /**
     * @return array<string, string>
     */
    public function getQueryParameter(): array
    {
        return [$this->field => $this->value];
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): void
    {
        $this->field = $field;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }
}
