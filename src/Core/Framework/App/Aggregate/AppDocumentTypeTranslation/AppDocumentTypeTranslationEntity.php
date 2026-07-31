<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppDocumentTypeTranslation;

use Shopware\Core\Framework\App\Aggregate\AppDocumentType\AppDocumentTypeEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class AppDocumentTypeTranslationEntity extends TranslationEntity
{
    protected ?string $label = null;

    protected ?AppDocumentTypeEntity $appDocumentType = null;

    protected string $appDocumentTypeId;

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getAppDocumentType(): ?AppDocumentTypeEntity
    {
        return $this->appDocumentType;
    }

    public function setAppDocumentType(?AppDocumentTypeEntity $appDocumentType): void
    {
        $this->appDocumentType = $appDocumentType;
    }

    public function getAppDocumentTypeId(): string
    {
        return $this->appDocumentTypeId;
    }

    public function setAppDocumentTypeId(string $appDocumentTypeId): void
    {
        $this->appDocumentTypeId = $appDocumentTypeId;
    }
}
