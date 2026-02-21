<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataLoader\CurrencyLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\CurrencyLoader\CurrencyLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\CurrencyLoader\CurrencyLoaderConfigSerializer;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[CoversClass(CurrencyLoaderConfigSerializer::class)]
class CurrencyLoaderConfigSerializerTest extends TestCase
{
    private CurrencyLoaderConfigSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new CurrencyLoaderConfigSerializer();
    }

    #[TestDox('returns currency source identifier')]
    public function testGetSourceReturnsCurrencyString(): void
    {
        static::assertSame('currency', CurrencyLoaderConfigSerializer::getSource());
    }

    #[TestDox('decodes array with valid associations into CurrencyLoaderConfig with associations')]
    public function testDecodeWithValidAssociationsReturnsCurrencyLoaderConfigWithAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => ['country', 'translations']]);

        static::assertInstanceOf(CurrencyLoaderConfig::class, $result);
        static::assertSame(['country', 'translations'], $result->associations);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('emptyOrNullAssociationsProvider')]
    #[TestDox('decodes absent or null associations into CurrencyLoaderConfig with empty associations')]
    public function testDecodeEmptyOrNullAssociationsReturnsEmptyAssociations(array $data): void
    {
        $result = $this->serializer->decode($data);

        static::assertInstanceOf(CurrencyLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function emptyOrNullAssociationsProvider(): iterable
    {
        yield 'absent associations key' => [[]];
        yield 'null associations value' => [['associations' => null]];
    }

    #[TestDox('decodes array with empty associations list into CurrencyLoaderConfig with empty associations')]
    public function testDecodeWithEmptyAssociationsListReturnsCurrencyLoaderConfigWithEmptyAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => []]);

        static::assertInstanceOf(CurrencyLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
    }

    #[TestDox('throws exception when associations value is not an array')]
    public function testDecodeWithNonArrayAssociationsThrowsException(): void
    {
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Field associations expected array');

        $this->serializer->decode(['associations' => 'country']);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidAssociationItemProvider')]
    #[TestDox('throws exception when an association item is invalid')]
    public function testDecodeWithInvalidAssociationItemThrowsException(array $data): void
    {
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Field associations.');

        $this->serializer->decode($data);
    }

    #[TestDox('encodes CurrencyLoaderConfig with associations into array with associations key')]
    public function testEncodeConfigWithAssociationsIncludesAssociationsKey(): void
    {
        $config = new CurrencyLoaderConfig(associations: ['country', 'translations']);

        $result = $this->serializer->encode($config);

        static::assertSame(['associations' => ['country', 'translations']], $result);
    }

    #[TestDox('encodes CurrencyLoaderConfig with no associations into empty array')]
    public function testEncodeConfigWithEmptyAssociationsReturnsEmptyArray(): void
    {
        $config = new CurrencyLoaderConfig();

        $result = $this->serializer->encode($config);

        static::assertSame([], $result);
    }

    #[TestDox('throws exception when encoding a non-CurrencyLoaderConfig config instance')]
    public function testEncodeWithWrongConfigTypeThrowsException(): void
    {
        $wrongConfig = new class extends AbstractContentDataLoaderConfig {
            public function getDecorated(): AbstractContentDataLoaderConfig
            {
                throw new DecorationPatternException(self::class);
            }
        };

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Field config expected');

        $this->serializer->encode($wrongConfig);
    }

    #[TestDox('round-trips a config with associations without data loss')]
    public function testDecodeAndEncodeAreInverseForConfigWithAssociations(): void
    {
        $original = ['associations' => ['country', 'translations']];

        $config = $this->serializer->decode($original);
        $encoded = $this->serializer->encode($config);

        static::assertSame($original, $encoded);
    }

    #[TestDox('round-trips an empty config without data loss')]
    public function testDecodeAndEncodeAreInverseForEmptyConfig(): void
    {
        $original = [];

        $config = $this->serializer->decode($original);
        $encoded = $this->serializer->encode($config);

        static::assertSame($original, $encoded);
    }

    #[TestDox('throws DecorationPatternException when getDecorated is called')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->expectExceptionMessage('The getDecorated() function of core class');

        $this->serializer->getDecorated();
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidAssociationItemProvider(): iterable
    {
        yield 'association item is not a string' => [['associations' => [42]]];
        yield 'association item is an empty string' => [['associations' => ['']]];
    }
}
