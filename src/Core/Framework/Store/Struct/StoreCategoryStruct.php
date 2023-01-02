<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('merchant-services')]
class StoreCategoryStruct extends StoreStruct
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int|null
     */
    protected $parent;

    /**
     * @var array
     */
    protected $details;

    public static function fromArray(array $data): StoreStruct
    {
        $category = new self();

        return $category->assign($data);
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

    public function getDetails(): array
    {
        return $this->details;
    }
}
