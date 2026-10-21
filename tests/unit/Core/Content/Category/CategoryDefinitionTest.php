<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategoryDefinition::class)]
class CategoryDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(CategoryDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(CategoryEntity::class, $definition->getEntityClass());
        static::assertSame(CategoryCollection::class, $definition->getCollectionClass());
        static::assertSame('6.0.0.0', $definition->since());
    }

    public function testDeprecatedCmsPageIdSwitchedFieldIsGone(): void
    {
        static::assertNull($this->createDefinition()->getFields()->get('cmsPageIdSwitched'));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedCmsPageIdSwitchedFieldStillExistsInTheLegacyMajor(): void
    {
        $field = $this->createDefinition()->getFields()->get('cmsPageIdSwitched');

        static::assertInstanceOf(BoolField::class, $field);
    }

    public function testTypeOnlyAcceptsTheKnownCategoryTypes(): void
    {
        $field = $this->createDefinition()->getFields()->get('type');
        static::assertInstanceOf(StringField::class, $field);

        $choice = $field->getFlag(Choice::class);
        static::assertInstanceOf(Choice::class, $choice);
        static::assertTrue($choice->isStrict());
        static::assertSame(
            [CategoryDefinition::TYPE_PAGE, CategoryDefinition::TYPE_LINK, CategoryDefinition::TYPE_FOLDER],
            $choice->getChoices(),
        );
    }

    private function createDefinition(): CategoryDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [CategoryDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(CategoryDefinition::ENTITY_NAME);
        static::assertInstanceOf(CategoryDefinition::class, $definition);

        return $definition;
    }
}
