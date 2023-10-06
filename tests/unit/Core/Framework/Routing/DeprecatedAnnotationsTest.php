<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Framework\Routing\Annotation\NoStore;

/**
 * @package core
 *
 * @internal
 *
 * @covers \Shopware\Core\Framework\Routing\Annotation\Entity
 * @covers \Shopware\Storefront\Framework\Cache\Annotation\HttpCache
 * @covers \Shopware\Storefront\Framework\Routing\Annotation\NoStore
 */
class DeprecatedAnnotationsTest extends TestCase
{
    /**
     * @DisabledFeatures("v6.6.0.0")
     */
    public function testDeprecatedAnnotationsCanBeConstructed(): void
    {
        $method = (new \ReflectionClass($this))->getMethod('testMethod');

        $annotations = (new AnnotationReader())->getMethodAnnotations($method);

        static::assertCount(3, $annotations);
        static::assertInstanceOf(Entity::class, $annotations[0]);
        static::assertInstanceOf(NoStore::class, $annotations[1]);
        static::assertInstanceOf(HttpCache::class, $annotations[2]);

        // make PHPStan happy that the private method is used
        $this->testMethod();
    }

    /**
     * @Entity("product")
     *
     * @NoStore()
     *
     * @HttpCache(maxAge=360, states={"logged-in", "cart-filled"})
     */
    private function testMethod(): void
    {
        // nothing, only for test
    }
}
