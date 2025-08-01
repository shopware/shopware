<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - reason:remove-entity - Will be removed
 */
#[Package('fundamentals@after-sales')]
class ImportExportProfileTranslationEntity extends TranslationEntity
{
    protected string $importExportProfileId;

    protected ?string $label = null;

    protected ImportExportProfileEntity $importExportProfile;

    public function getImportExportProfileId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->importExportProfileId;
    }

    public function setImportExportProfileId(string $importExportProfileId): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $this->importExportProfileId = $importExportProfileId;
    }

    public function getLabel(): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $this->label = $label;
    }

    public function getImportExportProfile(): ImportExportProfileEntity
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->importExportProfile;
    }

    public function setImportExportProfile(ImportExportProfileEntity $importExportProfile): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $this->importExportProfile = $importExportProfile;
    }
}
