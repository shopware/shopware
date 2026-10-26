<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MappingSourceResolutionContext::class)]
class MappingSourceResolutionContextTest extends TestCase
{
    public function testExposesTheElementAndResolutionInputsToAProvider(): void
    {
        $element = new StoredElement('element-1', 'Sw:Text');
        $rootValues = ['product' => 'product-data'];
        $loaderValues = ['name' => 'Shirt'];
        $cacheContext = new RenderingCacheContext();
        $context = new MappingSourceResolutionContext($element, $rootValues, $loaderValues, $cacheContext);

        static::assertSame($element, $context->element);
        static::assertSame($rootValues, $context->rootValues);
        static::assertSame($loaderValues, $context->loaderValues);
        static::assertSame($cacheContext, $context->cacheContext);
    }
}
