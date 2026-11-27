<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Api\Response\ResponseFactoryRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ResponseFactoryRegistry::class)]
class ResponseFactoryRegistryTest extends TestCase
{
    private ResponseFactoryInterface $jsonFactory;

    private ResponseFactoryInterface $jsonApiFactory;

    private ResponseFactoryRegistry $registry;

    protected function setUp(): void
    {
        $this->jsonFactory = static::createStub(ResponseFactoryInterface::class);
        $this->jsonFactory->method('supports')->willReturnCallback(static fn (string $contentType): bool => $contentType === 'application/json');
        $this->jsonApiFactory = static::createStub(ResponseFactoryInterface::class);
        $this->jsonApiFactory->method('supports')->willReturnCallback(static fn (string $contentType): bool => $contentType === 'application/vnd.api+json');
        $this->registry = new ResponseFactoryRegistry($this->jsonApiFactory, $this->jsonFactory);
    }

    #[DataProvider('acceptHeaders')]
    public function testResponseFormat(?string $accept, string $expectedType): void
    {
        $expectedFactory = $expectedType === 'application/json' ? $this->jsonFactory : $this->jsonApiFactory;

        static::assertSame($expectedFactory, $this->registry->getType($this->createRequest($accept)));
    }

    public static function acceptHeaders(): \Generator
    {
        yield 'missing Accept defaults to JSON' => [null, 'application/json'];
        yield 'wildcard defaults to JSON' => ['*/*', 'application/json'];
        yield 'explicit JSON' => ['application/json', 'application/json'];
        yield 'explicit JSON API' => ['application/vnd.api+json', 'application/vnd.api+json'];
        yield 'JSON API beats wildcard fallback' => ['application/vnd.api+json, */*;q=0.5', 'application/vnd.api+json'];
        yield 'JSON API preferred by quality' => ['application/json;q=0.5, application/vnd.api+json', 'application/vnd.api+json'];
        yield 'JSON preferred by quality' => ['application/vnd.api+json;q=0.5, application/json', 'application/json'];
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[DataProvider('legacyDefaults')]
    public function testLegacyDefault(?string $accept, bool $salesChannel): void
    {
        $expectedFactory = $salesChannel ? $this->jsonFactory : $this->jsonApiFactory;

        static::assertSame($expectedFactory, $this->registry->getType($this->createRequest($accept, $salesChannel)));
    }

    public static function legacyDefaults(): \Generator
    {
        yield 'Admin API wildcard defaults to JSON API' => ['*/*', false];
        yield 'sales channel wildcard defaults to JSON' => ['*/*', true];
        yield 'Admin API missing Accept defaults to JSON API' => [null, false];
        yield 'sales channel missing Accept defaults to JSON' => [null, true];
    }

    public function testUnsupportedMediaType(): void
    {
        $this->expectExceptionObject(ApiException::unsupportedResponseMediaTypes(['text/plain']));

        $this->registry->getType($this->createRequest('text/plain'));
    }

    private function createRequest(?string $accept, bool $salesChannel = false): Request
    {
        $request = new Request();
        if ($accept !== null) {
            $request->headers->set('Accept', $accept);
        }

        $source = $salesChannel ? new SalesChannelApiSource('01900000000000000000000000000000') : new AdminApiSource(null);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, new Context($source));

        return $request;
    }
}
