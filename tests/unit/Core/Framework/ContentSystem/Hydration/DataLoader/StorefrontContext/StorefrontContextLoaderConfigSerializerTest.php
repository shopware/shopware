<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader\StorefrontContext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\StorefrontContext\StorefrontContextLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StorefrontContextLoaderConfigSerializer::class)]
class StorefrontContextLoaderConfigSerializerTest extends TestCase
{
    public function testEmptyConfigRoundTrips(): void
    {
        $serializer = new StorefrontContextLoaderConfigSerializer();

        static::assertSame([], $serializer->encode($serializer->decode([])));
    }

    public function testUnexpectedConfigIsRejected(): void
    {
        $serializer = new StorefrontContextLoaderConfigSerializer();

        $this->expectExceptionObject(ContentSystemException::invalidFieldValueType('config', 'empty array', 'non-empty array'));

        $serializer->decode(['field' => 'customer']);
    }
}
