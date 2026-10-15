<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\ImportExportProfileTranslationEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ImportExportProfileTranslationEntity::class)]
class ImportExportProfileTranslationEntityTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyAccessors(): void
    {
        $entity = new ImportExportProfileTranslationEntity();
        $profile = new ImportExportProfileEntity();
        $entity->setLabel('label');
        $entity->setImportExportProfileId('profile');
        $entity->setImportExportProfile($profile);

        static::assertSame('label', $entity->getLabel());
        static::assertSame('profile', $entity->getImportExportProfileId());
        static::assertSame($profile, $entity->getImportExportProfile());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testConstructionThrowsInMajorMode(): void
    {
        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedClassMessage(ImportExportProfileTranslationEntity::class, 'v6.8.0.0')));

        new ImportExportProfileTranslationEntity();
    }

    public function testExistingEntityRemainsCallableInMajorMode(): void
    {
        $entity = Feature::withFeatureDisabled('v6.8.0.0', static fn () => new ImportExportProfileTranslationEntity());
        $entity->setLabel('label');

        static::assertSame('label', $entity->getLabel());
    }
}
