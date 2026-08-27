<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\Field\StoredElementListField;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Immutable;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentLayoutDefinition::class)]
class ContentLayoutDefinitionTest extends TestCase
{
    /**
     * Regression: LayoutWriteContext's command-to-memo pairing depends on
     * WriteCommandExtractor::createDataStack() never re-normalizing a content_layout row. The re-normalize
     * happens on create only, because createDataStack() returns early for a row that already exists. See the
     * LayoutWriteContext class docblock for the mechanism.
     */
    #[TestDox('declares no entity defaults')]
    public function testHasNoDefaults(): void
    {
        $definition = new ContentLayoutDefinition();

        static::assertSame([], $definition->getDefaults());
        static::assertSame([], $definition->getChildDefaults());
    }

    #[TestDox('declares id, name, version, layout and root_source plus the three RestrictDelete assignment associations')]
    public function testDeclaresItsFields(): void
    {
        $definition = new ContentLayoutDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $fields = $definition->getFields();

        $id = $fields->get('id');
        static::assertInstanceOf(IdField::class, $id);
        static::assertTrue($id->is(ApiAware::class));
        static::assertTrue($id->is(PrimaryKey::class));
        static::assertTrue($id->is(Required::class));

        $name = $fields->get('name');
        static::assertInstanceOf(StringField::class, $name);
        static::assertTrue($name->is(ApiAware::class));
        static::assertTrue($name->is(Required::class));

        $version = $fields->get('version');
        static::assertInstanceOf(StringField::class, $version);
        static::assertTrue($version->is(ApiAware::class));
        static::assertTrue($version->is(Required::class));

        $layout = $fields->get(ContentLayoutDefinition::LAYOUT_FIELD);
        static::assertInstanceOf(StoredElementListField::class, $layout);
        static::assertTrue($layout->is(ApiAware::class));
        static::assertTrue($layout->is(Required::class));

        $rootSource = $fields->get('rootSource');
        static::assertInstanceOf(StringField::class, $rootSource);
        static::assertTrue($rootSource->is(ApiAware::class));
        static::assertTrue($rootSource->is(Required::class));
        static::assertTrue($rootSource->is(Immutable::class));

        $productAssociation = $fields->get('productContentLayouts');
        static::assertInstanceOf(OneToManyAssociationField::class, $productAssociation);
        static::assertTrue($productAssociation->is(RestrictDelete::class));

        $categoryAssociation = $fields->get('categoryContentLayouts');
        static::assertInstanceOf(OneToManyAssociationField::class, $categoryAssociation);
        static::assertTrue($categoryAssociation->is(RestrictDelete::class));

        $landingPageAssociation = $fields->get('landingPageContentLayouts');
        static::assertInstanceOf(OneToManyAssociationField::class, $landingPageAssociation);
        static::assertTrue($landingPageAssociation->is(RestrictDelete::class));
    }
}
