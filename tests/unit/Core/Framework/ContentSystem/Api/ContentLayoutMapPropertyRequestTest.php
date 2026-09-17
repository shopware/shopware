<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutMapPropertyRequest;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentLayoutMapPropertyRequest::class)]
class ContentLayoutMapPropertyRequestTest extends TestCase
{
    public function testCarriesThePersistedMapOperationInput(): void
    {
        $source = ['type' => 'root', 'id' => 'product', 'path' => 'name'];
        $request = new ContentLayoutMapPropertyRequest('element-1', 'text', $source, 'version-1');

        static::assertSame('element-1', $request->elementId);
        static::assertSame('text', $request->propertyKey);
        static::assertSame($source, $request->source);
        static::assertSame('version-1', $request->expectedVersion);
    }
}
