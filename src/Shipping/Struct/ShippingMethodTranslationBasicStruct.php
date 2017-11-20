<?php declare(strict_types=1);

namespace Shopware\Shipping\Struct;

use Shopware\Api\Entity\Entity;

class ShippingMethodTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $shippingMethodUuid;

    /**
     * @var string
     */
    protected $languageUuid;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $comment;

    public function getShippingMethodUuid(): string
    {
        return $this->shippingMethodUuid;
    }

    public function setShippingMethodUuid(string $shippingMethodUuid): void
    {
        $this->shippingMethodUuid = $shippingMethodUuid;
    }

    public function getLanguageUuid(): string
    {
        return $this->languageUuid;
    }

    public function setLanguageUuid(string $languageUuid): void
    {
        $this->languageUuid = $languageUuid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }
}
