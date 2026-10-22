<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldEnumProviderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StrictChoiceResolver;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StrictChoiceResolver::class)]
#[Group('FieldSerializer')]
#[Group('DAL')]
class StrictChoiceResolverTest extends TestCase
{
    public function testAddsChoicesOfSupportingEnumProviders(): void
    {
        $resolver = new StrictChoiceResolver([
            self::createEnumProvider('category', 'type', ['custom']),
            self::createEnumProvider('product', 'type', ['other-entity']),
            self::createEnumProvider('category', 'linkType', ['other-field']),
        ]);

        $field = (new StringField('type', 'type'))->addFlags(new Choice(['page', 'link'], strict: true));

        static::assertSame(['page', 'link', 'custom'], $resolver->getStrictChoices($field, new CategoryDefinition()));
    }

    public function testAsksEnumProvidersForTheParentEntityOfATranslationDefinition(): void
    {
        $resolver = new StrictChoiceResolver([
            self::createEnumProvider(CategoryDefinition::ENTITY_NAME, 'name', ['from-parent']),
            self::createEnumProvider(CategoryTranslationDefinition::ENTITY_NAME, 'name', ['from-translation']),
        ]);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getByClassOrEntityName')->willReturn(new CategoryDefinition());
        $translationDefinition = new CategoryTranslationDefinition();
        $translationDefinition->compile($registry);

        $field = (new StringField('name', 'name'))->addFlags(new Choice(['fixed'], strict: true));

        static::assertSame(['fixed', 'from-parent'], $resolver->getStrictChoices($field, $translationDefinition));
    }

    public function testReturnsNullForNonStrictChoices(): void
    {
        $resolver = new StrictChoiceResolver([self::createEnumProvider('category', 'type', ['custom'])]);

        $field = (new StringField('type', 'type'))->addFlags(new Choice(['page', 'link']));

        static::assertNull($resolver->getStrictChoices($field, new CategoryDefinition()));
    }

    public function testReturnsNullWithoutChoiceFlag(): void
    {
        $resolver = new StrictChoiceResolver([self::createEnumProvider('category', 'type', ['custom'])]);

        static::assertNull($resolver->getStrictChoices(new StringField('type', 'type'), new CategoryDefinition()));
    }

    /**
     * @param list<string|bool|int|float> $choices
     */
    public static function createEnumProvider(string $entity, string $fieldName, array $choices): FieldEnumProviderInterface
    {
        return new class($entity, $fieldName, $choices) implements FieldEnumProviderInterface {
            /**
             * @param list<string|bool|int|float> $choices
             */
            public function __construct(
                private readonly string $entity,
                private readonly string $fieldName,
                private readonly array $choices,
            ) {
            }

            public function isSupported(string $entity, string $fieldName): bool
            {
                return $entity === $this->entity && $fieldName === $this->fieldName;
            }

            public function getChoices(): array
            {
                return $this->choices;
            }
        };
    }
}
