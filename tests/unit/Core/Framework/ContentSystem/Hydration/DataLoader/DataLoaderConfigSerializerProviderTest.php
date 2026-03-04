<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[CoversClass(DataLoaderConfigSerializerProvider::class)]
class DataLoaderConfigSerializerProviderTest extends TestCase
{
    #[TestDox('routes decode to the correct serializer and returns its result')]
    public function testDecodeRoutesToRegisteredSerializer(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $serializer = static::createStub(AbstractContentDataLoaderConfigSerializer::class);
        $serializer->method('decode')
            ->willReturn($config);

        $locator = new ServiceLocator(['entity' => fn () => $serializer]);
        $provider = new DataLoaderConfigSerializerProvider($locator);

        $result = $provider->decode('entity', ['key' => 'value']);

        static::assertSame($config, $result);
    }

    #[TestDox('routes encode to the correct serializer and returns its result')]
    public function testEncodeRoutesToRegisteredSerializer(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $encoded = ['foo' => 'bar'];

        $serializer = static::createStub(AbstractContentDataLoaderConfigSerializer::class);
        $serializer->method('encode')
            ->willReturn($encoded);

        $locator = new ServiceLocator(['entity' => fn () => $serializer]);
        $provider = new DataLoaderConfigSerializerProvider($locator);

        $result = $provider->encode('entity', $config);

        static::assertSame($encoded, $result);
    }

    #[TestDox('throws configSerializerNotRegistered when decode is called with an unknown source')]
    public function testDecodeThrowsForUnknownSource(): void
    {
        $locator = new ServiceLocator([]);
        $provider = new DataLoaderConfigSerializerProvider($locator);

        $this->expectExceptionObject(ContentSystemException::configSerializerNotRegistered('unknown_source'));

        $provider->decode('unknown_source', []);
    }

    #[TestDox('throws configSerializerNotRegistered when encode is called with an unknown source')]
    public function testEncodeThrowsForUnknownSource(): void
    {
        $locator = new ServiceLocator([]);
        $provider = new DataLoaderConfigSerializerProvider($locator);
        $config = static::createStub(AbstractContentDataLoaderConfig::class);

        $this->expectExceptionObject(ContentSystemException::configSerializerNotRegistered('unknown_source'));

        $provider->encode('unknown_source', $config);
    }
}
