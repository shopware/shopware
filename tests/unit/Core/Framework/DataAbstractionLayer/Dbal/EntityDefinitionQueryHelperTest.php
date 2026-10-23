<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityDefinitionQueryHelper::class)]
class EntityDefinitionQueryHelperTest extends TestCase
{
    #[DataProvider('provideTestGetRoot')]
    public function testGetRoot(string $accessor, ?string $expectedRoot): void
    {
        $definition = $this->getRegistry()->getByEntityName('product');

        static::assertEquals(
            $expectedRoot,
            EntityDefinitionQueryHelper::getRoot($accessor, $definition)
        );
    }

    #[DataProvider('provideGetAssociatedDefinition')]
    public function testGetAssociatedDefinition(string $accessor, string $expectedEntity): void
    {
        $definition = $this->getRegistry()->getByEntityName('product');

        static::assertEquals(
            $expectedEntity,
            EntityDefinitionQueryHelper::getAssociatedDefinition($definition, $accessor)->getEntityName()
        );
    }

    public static function provideTestGetRoot(): \Generator
    {
        yield 'no root' => ['name', null];
        yield 'with root' => ['categories.name', 'categories'];
        yield 'nested root' => ['product.categories.name', 'categories'];
        yield 'invalid root' => ['invalid.name', null];
    }

    public static function provideGetAssociatedDefinition(): \Generator
    {
        yield 'no root' => ['name', 'product'];
        yield 'with root' => ['categories.name', 'category'];
        yield 'many to many' => ['product.categories.name', 'category'];
        yield 'many to one' => ['product.manufacturer.name', 'product_manufacturer'];
        yield 'nested' => ['product.categories.translated.customFields.test', 'category'];
    }

    public function testEscapeQuotesAValidIdentifier(): void
    {
        static::assertSame('`product`', EntityDefinitionQueryHelper::escape('product'));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideDisallowedIdentifiers(): \Generator
    {
        yield 'backtick' => ['pro`duct'];
        yield 'question mark' => ['pro?duct'];
        yield 'colon' => ['pro:duct'];
        yield 'control character' => ["pro\nduct"];
        yield 'single quote' => ['pro\'duct'];
        yield 'double quote' => ['pro"duct'];
        yield 'double dash comment' => ['pro--duct'];
        yield 'backslash' => ['pro\\duct'];
        yield 'semicolon' => ['pro;duct'];
        yield 'block comment alias t/*' => ['t/*'];
        yield 'block comment close */' => ['a*/b'];
        // malformed UTF-8 makes preg_match('/\p{Cc}/u') return false; the NUL must still be rejected
        yield 'control character behind invalid byte' => ["a\xff\x00b"];
    }

    #[DataProvider('provideDisallowedIdentifiers')]
    public function testEscapeRejectsDisallowedIdentifierChars(string $identifier): void
    {
        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Backtick, quote, question mark, colon, semicolon, backslash, SQL comment marker, or control character not allowed in identifier');

        EntityDefinitionQueryHelper::escape($identifier);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideAllowedIdentifiers(): \Generator
    {
        yield 'plain' => ['product'];
        yield 'snake case storage name' => ['customer_tax'];
        yield 'dashed aggregation name' => ['price-count'];
        yield 'dotted key suffix' => ['my_agg.key'];
        yield 'nested root alias' => ['product.translation.override_1'];
        yield 'single dash is allowed' => ['a-b'];
        // lone slash/star cannot form a comment marker, so API-supplied labels keep working
        yield 'lone slash in label' => ['price/range'];
        yield 'lone star in label' => ['col*'];
    }

    #[DataProvider('provideAllowedIdentifiers')]
    public function testEscapeAcceptsValidIdentifiers(string $identifier): void
    {
        static::assertSame('`' . $identifier . '`', EntityDefinitionQueryHelper::escape($identifier));
    }

    /**
     * @return \Generator<string, array{string, bool}>
     */
    public static function provideJsonPaths(): \Generator
    {
        yield 'simple segment' => ['.foo', true];
        yield 'nested segments' => ['.foo.bar', true];
        yield 'hyphenated key' => ['.foo-bar', true];
        yield 'quoted dotted key' => ['."foo.bar"', true];
        // lone slash/star are inert inside the quote()-wrapped path, so JSON keys keep working
        yield 'allows lone slash' => ['.a/b', true];
        yield 'allows lone star' => ['.a*b', true];
        // the filter-side half of the advisory chain: */ closes the comment the alias opened
        yield 'rejects comment close */' => ['.a*/b', false];
        yield 'rejects comment open /*' => ['.a/*b', false];
        yield 'rejects control character' => [".a\nb", false];
        // malformed UTF-8 makes preg_match('/\p{Cc}/u') return false; must fail safe to rejected
        yield 'rejects control character behind invalid byte' => [".a\xff\x00b", false];
    }

    #[DataProvider('provideJsonPaths')]
    public function testIsValidJsonPath(string $path, bool $expected): void
    {
        static::assertSame($expected, EntityDefinitionQueryHelper::isValidJsonPath($path));
    }

    private function getRegistry(): DefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
            [
                ProductDefinition::class,
                ProductCategoryDefinition::class,
                CategoryTranslationDefinition::class,
                CategoryDefinition::class,
                ProductManufacturerDefinition::class,
                ProductTranslationDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }
}
