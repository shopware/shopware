<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\MapPropertyRequest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MapPropertyRequest::class)]
class MapPropertyRequestTest extends TestCase
{
    public function testValidatesAndCarriesTheDraftMapOperationInput(): void
    {
        $source = ['type' => 'root', 'id' => 'product', 'path' => 'name'];
        $layout = [['id' => 'element-1', 'component' => 'Sw:Text']];
        $request = new MapPropertyRequest('element-1', 'text', $source, 'product', $layout);
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        static::assertCount(0, $validator->validate($request));
        static::assertSame('element-1', $request->elementId);
        static::assertSame('text', $request->propertyKey);
        static::assertSame($source, $request->source);
        static::assertSame('product', $request->rootSource);
        static::assertSame($layout, $request->layout);
    }

    public function testRejectsAnEmptyRootSource(): void
    {
        $request = new MapPropertyRequest('element-1', 'text', [], '');
        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($request);

        static::assertCount(1, $violations);
        static::assertSame('rootSource', $violations->get(0)->getPropertyPath());
    }
}
