<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Mcp\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\App\Mcp\Xml\McpResource;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(McpResource::class)]
#[Package('framework')]
class McpResourceTest extends TestCase
{
    public function testFromXmlParsesAllFields(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $resources = $mcp->getResources();
        static::assertNotNull($resources);

        $resource = $resources->getResources()[0];
        static::assertSame('order-stats', $resource->getName());
        static::assertSame('app-example://order-stats', $resource->getUri());
        static::assertSame('https://app.example.com/mcp/resource/order-stats', $resource->getUrl());
        static::assertSame('application/json', $resource->getMimeType());
        static::assertSame([
            'en-GB' => 'Order Stats',
            'de-DE' => 'Bestellungsstatistiken',
        ], $resource->getLabel());
        static::assertSame(['en-GB' => 'Live order statistics'], $resource->getDescription());
    }

    public function testResourceWithoutDescriptionReturnsEmptyArray(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $resources = $mcp->getResources();
        static::assertNotNull($resources);

        $resource = $resources->getResources()[1];
        static::assertSame('product-list', $resource->getName());
        static::assertSame([], $resource->getDescription());
    }

    public function testToArrayContainsTranslations(): void
    {
        $resource = McpResource::fromArray([
            'name' => 'my-resource',
            'uri' => 'app://my-resource',
            'url' => 'https://example.com/mcp/resource',
            'mimeType' => 'application/json',
            'label' => ['en-GB' => 'My Resource', 'de-DE' => 'Meine Ressource'],
            'description' => ['en-GB' => 'Desc'],
        ]);

        $data = $resource->toArray('en-GB');

        static::assertSame('my-resource', $data['name']);
        static::assertSame('app://my-resource', $data['uri']);
        static::assertSame('https://example.com/mcp/resource', $data['url']);
        static::assertSame('application/json', $data['mimeType']);
        static::assertSame('My Resource', $data['label']['en-GB']);
        static::assertSame('Meine Ressource', $data['label']['de-DE']);
    }

    public function testFromArraySetsProperties(): void
    {
        $resource = McpResource::fromArray([
            'name' => 'test',
            'uri' => 'app://test',
            'url' => 'https://test.example.com/resource',
            'mimeType' => 'text/plain',
            'label' => ['en-GB' => 'Test'],
            'description' => [],
        ]);

        static::assertSame('test', $resource->getName());
        static::assertSame('app://test', $resource->getUri());
        static::assertSame('https://test.example.com/resource', $resource->getUrl());
        static::assertSame('text/plain', $resource->getMimeType());
    }

    public function testFromArrayWithoutMimeType(): void
    {
        $resource = McpResource::fromArray([
            'name' => 'no-mime',
            'uri' => 'app://no-mime',
            'url' => 'https://example.com/resource',
            'label' => ['en-GB' => 'No Mime'],
            'description' => [],
        ]);

        static::assertNull($resource->getMimeType());
    }
}
