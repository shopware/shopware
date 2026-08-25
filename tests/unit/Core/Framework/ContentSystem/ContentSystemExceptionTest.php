<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemException::class)]
class ContentSystemExceptionTest extends TestCase
{
    #[DataProvider('producesCorrectStatusAndErrorCodeProvider')]
    #[TestDox('produces correct status and error code for $_dataName')]
    public function testProducesCorrectStatusAndErrorCode(
        ContentSystemException $exception,
        int $expectedStatus,
        string $expectedErrorCode,
        string $expectedMessageFragment,
    ): void {
        static::assertSame($expectedStatus, $exception->getStatusCode());
        static::assertSame($expectedErrorCode, $exception->getErrorCode());
        static::assertStringContainsString($expectedMessageFragment, $exception->getMessage());
    }

    #[DataProvider('classifiesClientDefectProvider')]
    #[TestDox('classifies $_dataName')]
    public function testIsClientDefect(ContentSystemException $exception, bool $isClientDefect): void
    {
        static::assertSame($isClientDefect, ContentSystemException::isClientDefect($exception));
    }

    #[TestDox('pins the catalogue of client-defect error codes')]
    public function testClientDefectCodes(): void
    {
        $expected = [
            ContentSystemException::DATA_LOADER_NOT_REGISTERED,
            ContentSystemException::CONFIG_SERIALIZER_NOT_REGISTERED,
            ContentSystemException::UNKNOWN_LOADER_ENTITY,
            ContentSystemException::INVALID_FIELD_VALUE_TYPE,
            ContentSystemException::INVALID_FIELD_VALUE_RANGE,
            ContentSystemException::CONSUMER_ALIAS_WITHOUT_REDISTRIBUTE,
            ContentSystemException::PROPERTY_ALIAS_WITH_DOT_NOTATION,
            ContentSystemException::PROVIDER_DELIVERY_COLLISION,
            ContentSystemException::INVALID_MAP_KEY,
        ];

        $actual = ContentSystemException::CLIENT_DEFECT_CODES;
        sort($expected);
        sort($actual);

        static::assertSame($expected, $actual);
    }

    #[TestDox('rejects a non content-system throwable as a client defect')]
    public function testForeignThrowableIsNotAClientDefect(): void
    {
        static::assertFalse(ContentSystemException::isClientDefect(new \RuntimeException('boom')));
    }

    #[TestDox('propagates previous throwable when loading element type fails')]
    public function testPreservesPreviousThrowableOnLoadFailed(): void
    {
        $previous = new \RuntimeException('parse error');
        $e = ContentSystemException::elementTypeLoadFailed('test.yaml', 'invalid syntax', $previous);

        static::assertSame($previous, $e->getPrevious());
    }

    #[TestDox('propagates previous throwable when a data loader config is invalid')]
    public function testPreservesPreviousThrowableOnInvalidLoaderConfig(): void
    {
        $previous = new \RuntimeException('rootId expected non-empty string, got integer');
        $e = ContentSystemException::invalidLoaderConfig('navigation', $previous);

        static::assertSame($previous, $e->getPrevious());
    }

    #[TestDox('configSerializerNotRegistered keeps today\'s message verbatim without an element id, and names the element when one is given')]
    public function testConfigSerializerNotRegisteredMessageForm(): void
    {
        $withoutId = ContentSystemException::configSerializerNotRegistered('yaml');
        static::assertSame('Config serializer for source "yaml" is not registered', $withoutId->getMessage());
        static::assertSame(ContentSystemException::CONFIG_SERIALIZER_NOT_REGISTERED, $withoutId->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $withoutId->getStatusCode());

        $withId = ContentSystemException::configSerializerNotRegistered('yaml', 'elem-1');
        static::assertSame(
            'Config serializer for source "yaml" is not registered. Element ID: "elem-1"',
            $withId->getMessage()
        );
        static::assertSame(ContentSystemException::CONFIG_SERIALIZER_NOT_REGISTERED, $withId->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $withId->getStatusCode());
    }

    /**
     * @return iterable<string, array{ContentSystemException, bool}>
     */
    public static function classifiesClientDefectProvider(): iterable
    {
        // A code in the catalogue is reachable from the layout decode path (dataRequirements / acceptsContext),
        // so a client typo must become an invalid_config diagnostic, not a 500 that aborts the write. The exact
        // catalogue membership is pinned by a separate test.
        yield 'a code in the client-defect catalogue as a client defect' => [ContentSystemException::unknownLoaderEntity('prodct'), true];
        yield 'a provider delivery collision as a client defect' => [ContentSystemException::providerDeliveryCollision('item', 'product', 'category', 'el-1'), true];
        // A code outside the catalogue is an internal fault that must propagate, never relabelled as the client's mistake.
        yield 'a code outside the client-defect catalogue as an internal fault' => [ContentSystemException::invalidFieldType('A', 'B'), false];
        // A served layout is stored data, not client input, so a corrupt forest is an internal fault.
        yield 'a duplicate element id as an internal fault' => [ContentSystemException::duplicateElementId('repeated-id'), false];
    }

    /**
     * @return iterable<string, array{ContentSystemException, int, string, string}>
     */
    public static function producesCorrectStatusAndErrorCodeProvider(): iterable
    {
        yield 'data loader not registered' => [
            ContentSystemException::dataLoaderNotRegistered('product', 'Sw:Product:Card', 'elem-1'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__DATA_LOADER_NOT_REGISTERED',
            'product',
        ];

        yield 'preview payload invalid' => [
            ContentSystemException::previewPayloadInvalid('layout', 'array', 'string'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__PREVIEW_PAYLOAD_INVALID',
            'layout',
        ];

        yield 'config serializer not registered' => [
            ContentSystemException::configSerializerNotRegistered('yaml'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__CONFIG_SERIALIZER_NOT_REGISTERED',
            'yaml',
        ];

        yield 'invalid field type' => [
            ContentSystemException::invalidFieldType('StringField', 'IntField'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__INVALID_FIELD_TYPE',
            'StringField',
        ];

        yield 'invalid field value type' => [
            ContentSystemException::invalidFieldValueType('count', 'int', 'string'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__INVALID_FIELD_VALUE_TYPE',
            'count',
        ];

        yield 'invalid loader config' => [
            ContentSystemException::invalidLoaderConfig('navigation', new \RuntimeException('rootId expected non-empty string, got integer')),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__INVALID_FIELD_VALUE_TYPE',
            'navigation',
        ];

        yield 'invalid map key' => [
            ContentSystemException::invalidMapKey('ConfigMap', 'integer'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__INVALID_MAP_KEY',
            'ConfigMap',
        ];

        yield 'invalid map value' => [
            ContentSystemException::invalidMapValue('ConfigMap', 'foo', 'string', 'array'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__INVALID_MAP_VALUE',
            'foo',
        ];

        yield 'duplicate element id' => [
            ContentSystemException::duplicateElementId('repeated-id'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__DUPLICATE_ELEMENT_ID',
            'repeated-id',
        ];

        yield 'layout assignment not found' => [
            ContentSystemException::layoutAssignmentNotFound('product', 'prod-1', 'sc-1'),
            Response::HTTP_NOT_FOUND,
            'CONTENT_SYSTEM__LAYOUT_ASSIGNMENT_NOT_FOUND',
            'prod-1',
        ];

        yield 'layout not found' => [
            ContentSystemException::layoutNotFound('layout-1'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__LAYOUT_NOT_FOUND',
            'layout-1',
        ];

        yield 'element not found' => [
            ContentSystemException::elementNotFound('elem-1'),
            Response::HTTP_NOT_FOUND,
            'CONTENT_SYSTEM__ELEMENT_NOT_FOUND',
            'elem-1',
        ];

        yield 'no factory can handle' => [
            ContentSystemException::noFactoryCanHandle('/store-api/content/unknown'),
            Response::HTTP_NOT_FOUND,
            'CONTENT_SYSTEM__NO_FACTORY_CAN_HANDLE',
            '/store-api/content/unknown',
        ];

        yield 'invalid entity path' => [
            ContentSystemException::invalidEntityPath('product', 'bad//path', 'entity/id'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__INVALID_ENTITY_PATH',
            'bad//path',
        ];

        yield 'redistribute with dotted path' => [
            ContentSystemException::redistributeWithDottedPath('parent.child'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__REDISTRIBUTE_DOTTED_PATH',
            'parent.child',
        ];

        yield 'redistribute conflict' => [
            ContentSystemException::redistributeConflict('myKey'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__REDISTRIBUTE_CONFLICT',
            'redistribute:true and explicit providesContext',
        ];

        yield 'consumer alias without redistribute' => [
            ContentSystemException::consumerAliasWithoutRedistribute('myKey'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__CONSUMER_ALIAS_WITHOUT_REDISTRIBUTE',
            'has consumerAlias but redistribute is not true',
        ];

        yield 'contextPathNotResolvable without reason' => [
            ContentSystemException::contextPathNotResolvable('product.name', 'elem-1'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__CONTEXT_PATH_NOT_RESOLVABLE',
            'product.name',
        ];

        yield 'contextPathNotResolvable with reason' => [
            ContentSystemException::contextPathNotResolvable('product.name', 'elem-1', 'missing provider'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__CONTEXT_PATH_NOT_RESOLVABLE',
            'missing provider',
        ];

        yield 'property alias with dot notation' => [
            ContentSystemException::propertyAliasWithDotNotation('myKey', 'parent.child'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__PROPERTY_ALIAS_WITH_DOT_NOTATION',
            'has propertyAlias "parent.child" with dot notation',
        ];

        yield 'property alias collision' => [
            ContentSystemException::propertyAliasCollision('name', 'ctx1', 'ctx2'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__PROPERTY_ALIAS_COLLISION',
            'Each propertyAlias must be unique within an element',
        ];

        yield 'provider delivery collision' => [
            ContentSystemException::providerDeliveryCollision('item', 'product', 'category', 'el-1'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__PROVIDER_DELIVERY_COLLISION',
            'Each child-facing key must be unique within an element',
        ];

        yield 'missing extends annotation' => [
            ContentSystemException::missingExtendsAnnotation('App\Loader\MyLoader'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__MISSING_EXTENDS_ANNOTATION',
            'App\Loader\MyLoader',
        ];

        yield 'unsupported type node' => [
            ContentSystemException::unsupportedTypeNode('UnionTypeNode'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__UNSUPPORTED_TYPE_NODE',
            'UnionTypeNode',
        ];

        yield 'unresolvable type class' => [
            ContentSystemException::unresolvableTypeClass('SomeClass', 'App\Loader\MyLoader'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__UNRESOLVABLE_TYPE_CLASS',
            'SomeClass',
        ];

        yield 'routes already loaded' => [
            ContentSystemException::routesAlreadyLoaded(),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__ROUTES_ALREADY_LOADED',
            'already loaded',
        ];

        yield 'element type load failed' => [
            ContentSystemException::elementTypeLoadFailed('test.yaml', 'invalid syntax'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__ELEMENT_TYPE_LOAD_FAILED',
            'test.yaml',
        ];

        yield 'element type duplicate' => [
            ContentSystemException::elementTypeDuplicate('Sw:Product:Card', 'core', 'MyPlugin'),
            Response::HTTP_CONFLICT,
            'CONTENT_SYSTEM__ELEMENT_TYPE_DUPLICATE',
            'Sw:Product:Card',
        ];

        yield 'element types invalid with batch violations' => [
            ContentSystemException::elementTypesInvalid(
                new ConstraintViolationList([
                    new ConstraintViolation('must not be blank', null, [], null, '[Sw:Bad:A].label', null),
                    new ConstraintViolation('too short', null, [], null, '[Sw:Bad:B].description', null),
                ])
            ),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__ELEMENT_TYPES_INVALID',
            '[Sw:Bad:A].label: must not be blank; [Sw:Bad:B].description: too short',
        ];

        yield 'element type invalid filename' => [
            ContentSystemException::elementTypeInvalidFilename('bad segment', 'path/to/file.yaml'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__ELEMENT_TYPE_INVALID_FILENAME',
            'bad segment',
        ];

        yield 'element type not found' => [
            ContentSystemException::elementTypeNotFound('Sw:Unknown:Type'),
            Response::HTTP_NOT_FOUND,
            'CONTENT_SYSTEM__ELEMENT_TYPE_NOT_FOUND',
            'Sw:Unknown:Type',
        ];

        yield 'unknown entity type' => [
            ContentSystemException::unknownEntityType('mystery_entity'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__UNKNOWN_ENTITY_TYPE',
            'mystery_entity',
        ];

        yield 'entity type resolution unsupported' => [
            ContentSystemException::entityTypeResolutionUnsupported(),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__ENTITY_TYPE_RESOLUTION_UNSUPPORTED',
            'supportsEntityType() returns true',
        ];

        yield 'invalid layout structure with violations' => [
            ContentSystemException::invalidLayoutStructure(
                new ConstraintViolationList([
                    new ConstraintViolation('id must be a non-empty string', null, [], null, '[0].id', null),
                    new ConstraintViolation('component must be a non-empty string', null, [], null, '[1].component', null),
                ])
            ),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__INVALID_LAYOUT_STRUCTURE',
            '[0].id: id must be a non-empty string; [1].component: component must be a non-empty string',
        ];

        yield 'binding specification duplicate' => [
            ContentSystemException::bindingSpecificationDuplicate('media-picker', 'core', 'app:Acme'),
            Response::HTTP_CONFLICT,
            'CONTENT_SYSTEM__BINDING_SPECIFICATION_DUPLICATE',
            'media-picker',
        ];

        yield 'binding specification load failed' => [
            ContentSystemException::bindingSpecificationLoadFailed('/path/x.yaml', 'missing or empty "id"'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__BINDING_SPECIFICATION_LOAD_FAILED',
            '/path/x.yaml',
        ];

        yield 'binding specifications invalid' => [
            ContentSystemException::bindingSpecificationsInvalid(
                new ConstraintViolationList([
                    new ConstraintViolation('must not be blank', null, [], null, 'resolves[media]', null),
                ])
            ),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__BINDING_SPECIFICATIONS_INVALID',
            'resolves[media]',
        ];

        yield 'binding specification not found' => [
            ContentSystemException::bindingSpecificationNotFound('ghost'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__BINDING_SPECIFICATION_NOT_FOUND',
            'ghost',
        ];

        yield 'binding type mismatch' => [
            ContentSystemException::bindingTypeMismatch('spec-1', 'Sw:Media:Image', 'Sw:Product'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__BINDING_TYPE_MISMATCH',
            'Sw:Media:Image',
        ];
    }
}
