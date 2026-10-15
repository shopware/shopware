<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Aggregate\DocumentTypeTranslation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\Aggregate\DocumentTypeTranslation\DocumentTypeTranslationEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentTypeTranslationEntity::class)]
class DocumentTypeTranslationEntityTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyAccessors(): void
    {
        $entity = new DocumentTypeTranslationEntity();
        $type = new DocumentTypeEntity();
        $entity->setDocumentTypeId('type');
        $entity->setDocumentType($type);
        $entity->setName('Invoice');

        static::assertSame('type', $entity->getDocumentTypeId());
        static::assertSame($type, $entity->getDocumentType());
        static::assertSame('Invoice', $entity->getName());
    }

    /**
     * @deprecated tag:v6.9.0 - Remove with the major feature flag.
     */
    public function testConstructionThrowsWhenRemovalFlagIsActive(): void
    {
        Feature::registerFeature('v6.9.0.0');
        Feature::setActive('v6.9.0.0', true);
        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedClassMessage(DocumentTypeTranslationEntity::class, 'v6.9.0.0')));

        new DocumentTypeTranslationEntity();
    }

    public function testExistingEntityRemainsCallableWhenRemovalFlagIsActive(): void
    {
        $entity = new DocumentTypeTranslationEntity();
        Feature::registerFeature('v6.9.0.0');
        Feature::setActive('v6.9.0.0', true);
        $entity->setName('Invoice');

        static::assertSame('Invoice', $entity->getName());
    }
}
