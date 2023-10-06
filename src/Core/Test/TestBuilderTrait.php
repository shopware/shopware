<?php declare(strict_types=1);

namespace Shopware\Core\Test;

use Shopware\Core\Framework\Test\IdsCollection;

trait TestBuilderTrait
{
    protected IdsCollection $ids;

    /**
     * @var array<mixed>
     */
    protected array $_dynamic = [];

    /**
     * @param array<mixed>|object|string|float|int|bool|null $value
     */
    public function add(string $key, $value): self
    {
        $this->_dynamic[$key] = $value;

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function build(): array
    {
        $data = \get_object_vars($this);

        unset($data['ids'], $data['_dynamic']);

        $data = \array_merge($data, $this->_dynamic);

        return \array_filter($data, function ($value) {
            if (\is_array($value) && empty($value)) {
                return false;
            }

            return $value !== null;
        });
    }
}
