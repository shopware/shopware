<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\Aggregate\SalutationTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\System\Salutation\SalutationEntity;

class SalutationTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $salutationId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var SalutationEntity|null
     */
    protected $salutation;

    public function getSalutationId(): string
    {
        return $this->salutationId;
    }

    public function setSalutationId(string $salutationId): void
    {
        $this->salutationId = $salutationId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getSalutation(): ?SalutationEntity
    {
        return $this->salutation;
    }

    public function setSalutation(?SalutationEntity $salutation): void
    {
        $this->salutation = $salutation;
    }
}
