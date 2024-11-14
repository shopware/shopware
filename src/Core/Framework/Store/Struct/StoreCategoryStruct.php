<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class StoreCategoryStruct extends StoreStruct
{
    /**
     * @var int
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $id;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    /**
     * @var int|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $parent;

    /**
     * @var array<string, mixed>
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $details;

    /**
     * @return StoreCategoryStruct
     */
    public static function fromArray(array $data): StoreStruct
    {
        return (new self())->assign($data);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParent(): ?int
    {
        return $this->parent;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
