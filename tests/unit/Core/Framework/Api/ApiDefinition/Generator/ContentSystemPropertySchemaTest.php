<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApiFileLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OpenApiFileLoader::class)]
class ContentSystemPropertySchemaTest extends TestCase
{
    public function testRecursivePropertySpecificationsMatchTheSerializedShape(): void
    {
        $path = \dirname(__DIR__, 7) . '/src/Core/Framework/Api/ApiDefinition/Generator/Schema/AdminApi';
        $document = (new OpenApiFileLoader([$path]))->loadOpenapiSpecification();
        $document['propertySchema'] = $document['paths']['/_info/content-system-element-types.json']['get']['responses']['200']['content']['application/json']['schema']['properties']['types']['items']['properties']['properties']['additionalProperties'];
        $validator = new Validator(null, 20, false);
        $resolver = $validator->resolver();
        static::assertNotNull($resolver);
        static::assertTrue($resolver->registerRaw(Helper::toJSON($document), 'https://schema.shopware.test/admin.json'));
        $schema = 'https://schema.shopware.test/admin.json#/propertySchema';

        $leaf = new PropertySpecification('leaf', new PropertyType('string', false, null, null), false, '', '', null);
        $array = new PropertySpecification('array', new PropertyType('array', false, null, null, items: $leaf), false, '', '', null);
        $object = new PropertySpecification('object', new PropertyType('object', false, null, null, properties: ['nested' => $array]), false, '', '', null);
        $serialized = $object->toSchema();
        static::assertTrue($validator->validate(json_decode(json_encode($serialized, \JSON_THROW_ON_ERROR)), $schema)->isValid());
        static::assertTrue($validator->validate(json_decode(json_encode($leaf->toSchema(), \JSON_THROW_ON_ERROR)), $schema)->isValid());

        $empty = new PropertySpecification('empty', new PropertyType('object', false, null, null, properties: []), false, '', '', null);
        static::assertTrue($validator->validate(json_decode(json_encode($empty->toSchema(), \JSON_THROW_ON_ERROR)), $schema)->isValid());

        $serialized['properties']['nested']['items'] = new \stdClass();
        static::assertFalse($validator->validate(json_decode(json_encode($serialized, \JSON_THROW_ON_ERROR)), $schema)->isValid());
        $serialized['properties']['nested']['items'] = null;
        static::assertFalse($validator->validate(json_decode(json_encode($serialized, \JSON_THROW_ON_ERROR)), $schema)->isValid());
        $serialized['properties']['nested'] = (object) ['type' => null];
        static::assertFalse($validator->validate(json_decode(json_encode($serialized, \JSON_THROW_ON_ERROR)), $schema)->isValid());
    }

    public function testBreakpointFallbacksMatchTheServedMap(): void
    {
        $path = \dirname(__DIR__, 7) . '/src/Core/Framework/Api/ApiDefinition/Generator/Schema/AdminApi';
        $document = (new OpenApiFileLoader([$path]))->loadOpenapiSpecification();
        $document['fallbackSchema'] = $document['paths']['/_info/content-system-element-types.json']['get']['responses']['200']['content']['application/json']['schema']['properties']['breakpointFallbacks'];
        $validator = new Validator(null, 20, false);
        $resolver = $validator->resolver();
        static::assertNotNull($resolver);
        static::assertTrue($resolver->registerRaw(Helper::toJSON($document), 'https://schema.shopware.test/admin.json'));
        $schema = 'https://schema.shopware.test/admin.json#/fallbackSchema';

        $served = Breakpoint::fallbackMap();
        static::assertTrue($validator->validate(json_decode(json_encode($served, \JSON_THROW_ON_ERROR)), $schema)->isValid());

        $withoutBase = $served;
        unset($withoutBase['lg']);
        static::assertFalse($validator->validate(json_decode(json_encode($withoutBase, \JSON_THROW_ON_ERROR)), $schema)->isValid());

        $withUnknownBreakpoint = [...$served, 'print' => []];
        static::assertFalse($validator->validate(json_decode(json_encode($withUnknownBreakpoint, \JSON_THROW_ON_ERROR)), $schema)->isValid());
    }
}
