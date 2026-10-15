<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Aggregate\DocumentType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\Aggregate\DocumentTypeTranslation\DocumentTypeTranslationCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection;
use Shopware\Core\Checkout\DocumentV2\DocumentCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentTypeEntity::class)]
class DocumentTypeEntityTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyAccessors(): void
    {
        $entity = new DocumentTypeEntity();
        $translations = new DocumentTypeTranslationCollection();
        $documents = new DocumentCollection();
        $configs = new DocumentBaseConfigCollection();
        $salesChannels = new DocumentBaseConfigSalesChannelCollection();
        $entity->setName('Invoice');
        $entity->setTechnicalName('invoice');
        $entity->setTranslations($translations);
        $entity->setDocuments($documents);
        $entity->setDocumentBaseConfigs($configs);
        $entity->setDocumentBaseConfigSalesChannels($salesChannels);

        static::assertSame('Invoice', $entity->getName());
        static::assertSame('invoice', $entity->getTechnicalName());
        static::assertSame($translations, $entity->getTranslations());
        static::assertSame($documents, $entity->getDocuments());
        static::assertSame($configs, $entity->getDocumentBaseConfigs());
        static::assertSame($salesChannels, $entity->getDocumentBaseConfigSalesChannels());
    }

    /**
     * @deprecated tag:v6.9.0 - Remove with the major feature flag.
     */
    public function testConstructionThrowsWhenRemovalFlagIsActive(): void
    {
        Feature::registerFeature('v6.9.0.0');
        Feature::setActive('v6.9.0.0', true);
        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedClassMessage(DocumentTypeEntity::class, 'v6.9.0.0')));

        new DocumentTypeEntity();
    }

    public function testExistingEntityRemainsCallableWhenRemovalFlagIsActive(): void
    {
        $entity = new DocumentTypeEntity();
        Feature::registerFeature('v6.9.0.0');
        Feature::setActive('v6.9.0.0', true);
        $entity->setName('Invoice');

        static::assertSame('Invoice', $entity->getName());
    }
}
