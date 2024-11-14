<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
class ImportExportProfileTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $importExportProfileId;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $label;

    /**
     * @var ImportExportProfileEntity
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $importExportProfile;

    public function getImportExportProfileId(): string
    {
        return $this->importExportProfileId;
    }

    public function setImportExportProfileId(string $importExportProfileId): void
    {
        $this->importExportProfileId = $importExportProfileId;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getImportExportProfile(): ImportExportProfileEntity
    {
        return $this->importExportProfile;
    }

    public function setImportExportProfile(ImportExportProfileEntity $importExportProfile): void
    {
        $this->importExportProfile = $importExportProfile;
    }
}
