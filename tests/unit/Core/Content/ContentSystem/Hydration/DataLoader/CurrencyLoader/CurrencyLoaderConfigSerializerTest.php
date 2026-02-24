<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataLoader\CurrencyLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWithJson;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\CurrencyLoader\CurrencyLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\CurrencyLoader\CurrencyLoaderConfigSerializer;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\StubLoaderConfig;

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
    #[TestWithJson('[{}]', 'absent associations key')]
    #[TestWithJson('[{"associations": null}]', 'null associations value')]
    #[TestDox('decodes absent or null associations into CurrencyLoaderConfig with empty associations')]
    public function testDecodeEmptyOrNullAssociationsReturnsEmptyAssociations(array $data): void
    {
        $result = $this->serializer->decode($data);

        static::assertInstanceOf(CurrencyLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
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
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('associations', 'array', 'string')
        );

        $this->serializer->decode(['associations' => 'country']);
    }

    #[TestDox('throws exception when an association item is not a string')]
    public function testDecodeWithNonStringAssociationItemThrowsException(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('associations.0', 'non-empty string', 'integer')
        );

        $this->serializer->decode(['associations' => [42]]);
    }

    #[TestDox('throws exception when an association item is an empty string')]
    public function testDecodeWithEmptyStringAssociationItemThrowsException(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('associations.0', 'non-empty string', 'string')
        );

        $this->serializer->decode(['associations' => ['']]);
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
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('config', CurrencyLoaderConfig::class, StubLoaderConfig::class)
        );

        $this->serializer->encode(new StubLoaderConfig());
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
}
