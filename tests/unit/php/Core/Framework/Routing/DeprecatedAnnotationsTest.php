<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
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
     *
     * @Entity("product")
     * @NoStore()
     * @HttpCache(maxAge=360, states={"logged-in", "cart-filled"})
     */
    public function testDeprecatedAnnotationsCanBeConstructed(): void
    {
        AnnotationRegistry::registerLoader('class_exists');

        $reflectionClass = new \ReflectionClass($this);
        $method = $reflectionClass->getMethod('testDeprecatedAnnotationsCanBeConstructed');

        $reader = new AnnotationReader();
        $annotations = $reader->getMethodAnnotations($method);

        static::assertCount(4, $annotations);
        static::assertInstanceOf(Entity::class, $annotations[1]);
        static::assertInstanceOf(NoStore::class, $annotations[2]);
        static::assertInstanceOf(HttpCache::class, $annotations[3]);
    }
}
