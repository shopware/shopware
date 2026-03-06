<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Language\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWithJson;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\Language\LanguageException;
use Shopware\Core\System\Language\ContentSystem\DataLoader\LanguageLoaderConfig;
use Shopware\Core\System\Language\ContentSystem\DataLoader\LanguageLoaderConfigSerializer;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;

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

    /**
     * @param array<string, mixed> $data
     */
    #[TestWithJson('[{}]', 'absent associations key')]
    #[TestWithJson('[{"associations": null}]', 'null associations value')]
    #[TestDox('decodes absent or null associations into LanguageLoaderConfig with empty associations')]
    public function testDecodeEmptyOrNullAssociationsReturnsEmptyAssociations(array $data): void
    {
        $result = $this->serializer->decode($data);

        static::assertInstanceOf(LanguageLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
    }

    #[TestDox('throws exception when associations value is not an array')]
    public function testDecodeWithNonArrayAssociationsThrowsException(): void
    {
        $this->expectExceptionObject(
            LanguageException::invalidFieldValueType('associations', 'array', 'string')
        );

        $this->serializer->decode(['associations' => 'locale']);
    }

    #[TestDox('throws exception when an association item is not a string')]
    public function testDecodeWithNonStringAssociationItemThrowsException(): void
    {
        $this->expectExceptionObject(
            LanguageException::invalidFieldValueType('associations.0', 'non-empty string', 'integer')
        );

        $this->serializer->decode(['associations' => [42]]);
    }

    #[TestDox('throws exception when an association item is an empty string')]
    public function testDecodeWithEmptyStringAssociationItemThrowsException(): void
    {
        $this->expectExceptionObject(
            LanguageException::invalidFieldValueType('associations.0', 'non-empty string', 'string')
        );

        $this->serializer->decode(['associations' => ['']]);
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
        $this->expectExceptionObject(
            LanguageException::invalidFieldValueType('config', LanguageLoaderConfig::class, StubLoaderConfig::class)
        );

        $this->serializer->encode(new StubLoaderConfig());
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
}
