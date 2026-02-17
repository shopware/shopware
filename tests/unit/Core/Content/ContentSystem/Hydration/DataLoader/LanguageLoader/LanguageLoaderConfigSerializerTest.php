<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataLoader\LanguageLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\LanguageLoader\LanguageLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\LanguageLoader\LanguageLoaderConfigSerializer;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[CoversClass(LanguageLoaderConfigSerializer::class)]
class LanguageLoaderConfigSerializerTest extends TestCase
{
    private LanguageLoaderConfigSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new LanguageLoaderConfigSerializer();
    }

    #[TestDox('returns language source identifier')]
    public function testGetSourceReturnsLanguageString(): void
    {
        static::assertSame('language', LanguageLoaderConfigSerializer::getSource());
    }

    #[TestDox('decodes empty array into LanguageLoaderConfig with empty associations')]
    public function testDecodeEmptyArrayReturnsLanguageLoaderConfigWithEmptyAssociations(): void
    {
        $result = $this->serializer->decode([]);

        static::assertInstanceOf(LanguageLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
    }

    #[TestDox('decodes array with null associations into LanguageLoaderConfig with empty associations')]
    public function testDecodeWithNullAssociationsReturnsLanguageLoaderConfigWithEmptyAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => null]);

        static::assertInstanceOf(LanguageLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
    }

    #[TestDox('decodes array with valid associations into LanguageLoaderConfig with associations')]
    public function testDecodeWithValidAssociationsReturnsLanguageLoaderConfigWithAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => ['locale', 'translations']]);

        static::assertInstanceOf(LanguageLoaderConfig::class, $result);
        static::assertSame(['locale', 'translations'], $result->associations);
    }

    #[TestDox('decodes array with empty associations list into LanguageLoaderConfig with empty associations')]
    public function testDecodeWithEmptyAssociationsListReturnsLanguageLoaderConfigWithEmptyAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => []]);

        static::assertInstanceOf(LanguageLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
    }

    #[TestDox('throws exception when associations value is not an array')]
    public function testDecodeWithNonArrayAssociationsThrowsException(): void
    {
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Field associations expected array');

        $this->serializer->decode(['associations' => 'locale']);
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

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidAssociationItemProvider(): array
    {
        return [
            'non-string item triggers type validation' => [['associations' => [42]]],
            'empty string item triggers empty check' => [['associations' => ['']]],
        ];
    }

    #[TestDox('encodes LanguageLoaderConfig with no associations into empty array')]
    public function testEncodeConfigWithEmptyAssociationsReturnsEmptyArray(): void
    {
        $config = new LanguageLoaderConfig();

        $result = $this->serializer->encode($config);

        static::assertSame([], $result);
    }

    #[TestDox('encodes LanguageLoaderConfig with associations into array with associations key')]
    public function testEncodeConfigWithAssociationsIncludesAssociationsKey(): void
    {
        $config = new LanguageLoaderConfig(associations: ['locale', 'translations']);

        $result = $this->serializer->encode($config);

        static::assertSame(['associations' => ['locale', 'translations']], $result);
    }

    #[TestDox('throws exception when encoding a non-LanguageLoaderConfig config instance')]
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
        $original = ['associations' => ['locale', 'translations']];

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
