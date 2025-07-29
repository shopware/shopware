<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed
 */
#[Package('fundamentals@after-sales')]
class ImportExportProfileTranslationEntity extends TranslationEntity
{
    protected string $importExportProfileId;

    protected ?string $label = null;

    protected ImportExportProfileEntity $importExportProfile;

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    public function getImportExportProfileId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->importExportProfileId;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    public function setImportExportProfileId(string $importExportProfileId): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $this->importExportProfileId = $importExportProfileId;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    public function getLabel(): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->label;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    public function setLabel(?string $label): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $this->label = $label;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    public function getImportExportProfile(): ImportExportProfileEntity
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->importExportProfile;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    public function setImportExportProfile(ImportExportProfileEntity $importExportProfile): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $this->importExportProfile = $importExportProfile;
    }
}
