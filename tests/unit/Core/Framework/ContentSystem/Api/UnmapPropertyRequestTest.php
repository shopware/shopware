<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\UnmapPropertyRequest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UnmapPropertyRequest::class)]
class UnmapPropertyRequestTest extends TestCase
{
    public function testRootSourceIsOptionalForDraftUnmap(): void
    {
        $request = new UnmapPropertyRequest('element-1', 'text');
        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($request);

        static::assertCount(0, $violations);
        static::assertSame([], $request->layout);
        static::assertNull($request->rootSource);
    }

    public function testCarriesAnOptionalSourceAndLayout(): void
    {
        $layout = [['id' => 'element-1', 'component' => 'Sw:Text']];
        $request = new UnmapPropertyRequest('element-1', 'text', $layout, 'product');

        static::assertSame($layout, $request->layout);
        static::assertSame('product', $request->rootSource);
    }
}
