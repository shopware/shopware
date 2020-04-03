<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Mapping;

use Shopware\Core\Framework\Struct\Struct;

class Mapping extends Struct
{
    /**
     * @var string
     */
    protected $key;

    /**
     * @var string
     */
    protected $mappedKey;

    /**
     * @var mixed|null
     */
    protected $default;

    /**
     * @var mixed|null
     */
    protected $mappedDefault;

    public function __construct(string $key, ?string $mappedKey = null, $default = null, $mappedDefault = null)
    {
        $this->key = $key;
        $this->mappedKey = $mappedKey ?? $key;
        $this->default = $default;
        $this->mappedDefault = $mappedDefault;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getMappedKey(): string
    {
        return $this->mappedKey;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function getMappedDefault()
    {
        return $this->mappedDefault;
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['key'])) {
            throw new \InvalidArgumentException('key is required in mapping');
        }

        $mapping = new self($data['key']);
        $mapping->assign($data);

        return $mapping;
    }
}
