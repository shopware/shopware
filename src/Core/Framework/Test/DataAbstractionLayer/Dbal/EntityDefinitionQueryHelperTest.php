<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class EntityDefinitionQueryHelperTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @dataProvider getData
     *
     * @param array<string> $expected
     */
    public function testGetFieldsOfAccessor(
        string $class,
        string $accessor,
        array $expected,
        bool $resolveTranslated = true
    ): void {
        $definition = $this->getContainer()->get(DefinitionInstanceRegistry::class)->get($class);
        $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $accessor, $resolveTranslated);

        $actual = [];
        foreach ($fields as $field) {
            $actual[] = $field->getPropertyName();
        }

        static::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array<int, array<int, string>|string|false>>
     */
    public static function getData(): iterable
    {
        yield 'With product prefix and skippable fields' => [
            ProductDefinition::class,
            'product.options.first.group.extension.foo.bar',
            [
                'options',
                'group',
            ],
        ];

        yield 'With skippable field' => [
            ProductDefinition::class,
            'options.first.group.name',
            [
                'options',
                'group',
                'name',
            ],
        ];

        yield 'With translations field' => [
            ProductDefinition::class,
            'translations.description',
            [
                'translations',
                'description',
            ],
        ];

        yield 'With resolved translated field' => [
            ProductStreamDefinition::class,
            'description.invalid',
            [
                'description',
            ],
        ];

        yield 'Without resolved translated field' => [
            ProductStreamDefinition::class,
            'description.invalid',
            [
                'description',
                'invalid',
            ],
            false,
        ];

        yield 'With custom fields without valid field behind' => [
            ProductDefinition::class,
            'customFields.foo.bar',
            [
                'customFields',
            ],
        ];

        yield 'With custom fields with valid field behind' => [
            ProductDefinition::class,
            'customFields.name',
            [
                'customFields',
                'name',
            ],
        ];

        yield 'With json field without valid field behind' => [
            ProductDefinition::class,
            'variantListingConfig.foo.bar',
            [
                'variantListingConfig',
            ],
        ];
    }
}
