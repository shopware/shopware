<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Aggregate\DocumentTypeTranslation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentTypeTranslation\DocumentTypeTranslationCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentTypeTranslation\DocumentTypeTranslationDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentTypeTranslation\DocumentTypeTranslationEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentTypeTranslationDefinition::class)]
class DocumentTypeTranslationDefinitionTest extends TestCase
{
    public function testMetadataRemainsCallableWithPendingRemoval(): void
    {
        $definition = new DocumentTypeTranslationDefinition();

        static::assertSame('document_type_translation', $definition->getEntityName());
        static::assertSame(DocumentTypeTranslationCollection::class, $definition->getCollectionClass());
        static::assertSame(DocumentTypeTranslationEntity::class, $definition->getEntityClass());
        static::assertSame('6.0.0.0', $definition->since());
    }

    /**
     * @deprecated tag:v6.9.0 - Remove with the major feature flag.
     */
    public function testConstructionThrowsWhenRemovalFlagIsActive(): void
    {
        Feature::registerFeature('v6.9.0.0');
        Feature::setActive('v6.9.0.0', true);
        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedClassMessage(DocumentTypeTranslationDefinition::class, 'v6.9.0.0')));

        new DocumentTypeTranslationDefinition();
    }

    public function testExistingMetadataRemainsCallableWhenRemovalFlagIsActive(): void
    {
        $definition = new DocumentTypeTranslationDefinition();
        Feature::registerFeature('v6.9.0.0');
        Feature::setActive('v6.9.0.0', true);

        static::assertSame('document_type_translation', $definition->getEntityName());
        static::assertSame(DocumentTypeTranslationCollection::class, $definition->getCollectionClass());
        static::assertSame(DocumentTypeTranslationEntity::class, $definition->getEntityClass());
    }
}
