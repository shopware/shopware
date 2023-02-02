<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
class KeyValuePair
{
    /**
     * @var string
     */
    private $key;

    private $value;

    /**
     * @var bool
     */
    private $isRaw;

    public function __construct(string $key, $value, bool $isRaw)
    {
        $this->key = $key;
        $this->value = $value;
        $this->isRaw = $isRaw;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function isRaw(): bool
    {
        return $this->isRaw;
    }

    public function setValue($value): void
    {
        $this->isRaw = false;
        $this->value = $value;
    }
}
