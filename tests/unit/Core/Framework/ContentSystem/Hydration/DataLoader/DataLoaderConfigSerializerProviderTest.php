<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
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

    #[TestDox('resolves placeholder tokens in the raw config before decoding when values are provided')]
    public function testDecodeResolvesPlaceholdersWhenValuesProvided(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $captured = null;
        $serializer = static::createStub(AbstractContentDataLoaderConfigSerializer::class);
        $serializer->method('decode')->willReturnCallback(
            static function (array $data) use (&$captured, $config): AbstractContentDataLoaderConfig {
                $captured = $data;

                return $config;
            }
        );

        $locator = new ServiceLocator(['breadcrumb' => fn () => $serializer]);
        $provider = new DataLoaderConfigSerializerProvider($locator);

        $result = $provider->decode(
            'breadcrumb',
            ['type' => '{{entityType}}', 'nested' => ['id' => '{{productId}}'], 'keep' => 'static'],
            PlaceholderValues::from(['entityType' => 'category', 'productId' => 'p-1']),
        );

        static::assertSame($config, $result);
        static::assertSame(
            ['type' => 'category', 'nested' => ['id' => 'p-1'], 'keep' => 'static'],
            $captured,
        );
    }

    #[TestDox('leaves the raw config untouched when no placeholder values are provided')]
    public function testDecodeLeavesConfigUntouchedWithoutValues(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $captured = null;
        $serializer = static::createStub(AbstractContentDataLoaderConfigSerializer::class);
        $serializer->method('decode')->willReturnCallback(
            static function (array $data) use (&$captured, $config): AbstractContentDataLoaderConfig {
                $captured = $data;

                return $config;
            }
        );

        $locator = new ServiceLocator(['entity' => fn () => $serializer]);
        $provider = new DataLoaderConfigSerializerProvider($locator);

        $provider->decode('entity', ['type' => '{{entityType}}']);

        static::assertSame(['type' => '{{entityType}}'], $captured);
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

    #[TestDox('re-classifies a domain serializer HttpException as invalidLoaderConfig, chaining the original')]
    public function testDecodeReclassifiesForeignHttpExceptionAsInvalidLoaderConfig(): void
    {
        $domainException = new class(Response::HTTP_BAD_REQUEST, 'CATEGORY__INVALID_FIELD_VALUE_TYPE', 'rootId expected non-empty string, got integer') extends HttpException {
        };

        $serializer = static::createStub(AbstractContentDataLoaderConfigSerializer::class);
        $serializer->method('decode')
            ->willThrowException($domainException);

        $locator = new ServiceLocator(['navigation' => fn () => $serializer]);
        $provider = new DataLoaderConfigSerializerProvider($locator);

        try {
            $provider->decode('navigation', ['rootId' => 1]);
            static::fail('Expected a ContentSystemException to be thrown.');
        } catch (ContentSystemException $e) {
            static::assertTrue(ContentSystemException::isClientDefect($e));
            static::assertSame($domainException, $e->getPrevious());
        }
    }

    #[TestDox('passes through a ContentSystemException thrown by the serializer unchanged')]
    public function testDecodePassesThroughContentSystemExceptionUnchanged(): void
    {
        $original = ContentSystemException::unknownLoaderEntity('prodct');

        $serializer = static::createStub(AbstractContentDataLoaderConfigSerializer::class);
        $serializer->method('decode')
            ->willThrowException($original);

        $locator = new ServiceLocator(['entity' => fn () => $serializer]);
        $provider = new DataLoaderConfigSerializerProvider($locator);

        try {
            $provider->decode('entity', []);
            static::fail('Expected the original ContentSystemException to be thrown.');
        } catch (ContentSystemException $e) {
            static::assertSame($original, $e);
        }
    }

    #[TestDox('rethrows a ContentSystemException from the serializer unchanged')]
    public function testEncodeRethrowsContentSystemExceptionUnchanged(): void
    {
        $serializer = static::createStub(AbstractContentDataLoaderConfigSerializer::class);
        $original = ContentSystemException::invalidFieldValueType('config', 'array', 'string');
        $serializer->method('encode')->willThrowException($original);

        $provider = new DataLoaderConfigSerializerProvider(new ServiceLocator(['entity' => static fn () => $serializer]));

        try {
            $provider->encode('entity', static::createStub(AbstractContentDataLoaderConfig::class));
            static::fail('Expected ContentSystemException.');
        } catch (ContentSystemException $e) {
            static::assertSame($original, $e);
        }
    }

    #[TestDox('reclassifies a non-ContentSystemException HttpException as invalidLoaderConfig')]
    public function testEncodeReclassifiesForeignHttpExceptionAsInvalidLoaderConfig(): void
    {
        $domainException = new class(Response::HTTP_BAD_REQUEST, 'DOMAIN__BAD', 'bad config') extends HttpException {};
        $serializer = static::createStub(AbstractContentDataLoaderConfigSerializer::class);
        $serializer->method('encode')->willThrowException($domainException);

        $provider = new DataLoaderConfigSerializerProvider(new ServiceLocator(['entity' => static fn () => $serializer]));

        try {
            $provider->encode('entity', static::createStub(AbstractContentDataLoaderConfig::class));
            static::fail('Expected ContentSystemException.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::INVALID_FIELD_VALUE_TYPE, $e->getErrorCode());
            static::assertSame($domainException, $e->getPrevious());
        }
    }

    #[TestDox('lets a bare non-HttpException from the serializer propagate unwrapped')]
    public function testEncodeLetsBarePhpExceptionPropagate(): void
    {
        $serializer = static::createStub(AbstractContentDataLoaderConfigSerializer::class);
        $serializer->method('encode')->willThrowException(new \RuntimeException('boom'));

        $provider = new DataLoaderConfigSerializerProvider(new ServiceLocator(['entity' => static fn () => $serializer]));

        $this->expectException(\RuntimeException::class);
        $provider->encode('entity', static::createStub(AbstractContentDataLoaderConfig::class));
    }
}
