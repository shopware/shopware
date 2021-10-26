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

    /**
     * @var bool
     */
    protected $requiredByUser;

    /**
     * @var bool
     */
    protected $useDefaultValue;

    /**
     * @var mixed|null
     */
    protected $defaultValue;

    protected int $position;

    /**
     * @param mixed|null $defaultValue
     */
    public function __construct(
        string $key,
        ?string $mappedKey = null,
        int $position = 0,
        $default = null,
        $mappedDefault = null,
        bool $requiredByUser = false,
        bool $useDefaultValue = false,
        $defaultValue = null
    ) {
        $this->key = $key;
        $this->mappedKey = $mappedKey ?? $key;
        $this->default = $default;
        $this->mappedDefault = $mappedDefault;
        $this->requiredByUser = $requiredByUser;
        $this->useDefaultValue = $useDefaultValue;
        $this->defaultValue = $defaultValue;
        $this->position = $position;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getMappedKey(): string
    {
        return $this->mappedKey;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_8097) Use getDefaultValue() instead if you want the user specified default value.
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_8097) Use getDefaultValue() instead if you want the user specified default value.
     */
    public function getMappedDefault()
    {
        return $this->mappedDefault;
    }

    /**
     * @internal (flag:FEATURE_NEXT_8097)
     */
    public function isRequiredByUser(): bool
    {
        return $this->requiredByUser;
    }

    /**
     * @internal (flag:FEATURE_NEXT_8097)
     */
    public function isUseDefaultValue(): bool
    {
        return $this->useDefaultValue;
    }

    /**
     * @internal (flag:FEATURE_NEXT_8097)
     *
     * @return mixed|null
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
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
