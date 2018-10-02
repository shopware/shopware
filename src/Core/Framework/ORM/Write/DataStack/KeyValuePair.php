<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\DataStack;

class KeyValuePair
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var mixed
     */
    private $value;

    /**
     * @var bool
     */
    private $isRaw;

    /**
     * @param string $key
     * @param mixed  $value
     * @param bool   $isRaw
     */
    public function __construct(string $key, $value, bool $isRaw)
    {
        $this->key = $key;
        $this->value = $value;
        $this->isRaw = $isRaw;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return bool
     */
    public function isRaw(): bool
    {
        return $this->isRaw;
    }
}
