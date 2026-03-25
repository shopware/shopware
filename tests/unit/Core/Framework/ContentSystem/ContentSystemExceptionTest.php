<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
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

    #[TestDox('propagates previous throwable when loading element type fails')]
    public function testPreservesPreviousThrowableOnLoadFailed(): void
    {
        $previous = new \RuntimeException('parse error');
        $e = ContentSystemException::elementTypeLoadFailed('test.yaml', 'invalid syntax', $previous);

        static::assertSame($previous, $e->getPrevious());
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

        yield 'path integrity violation' => [
            ContentSystemException::pathIntegrityViolation('duplicate key'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__PATH_INTEGRITY_VIOLATION',
            'duplicate key',
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
            'myKey',
        ];

        yield 'consumer alias without redistribute' => [
            ContentSystemException::consumerAliasWithoutRedistribute('myKey'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__CONSUMER_ALIAS_WITHOUT_REDISTRIBUTE',
            'myKey',
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
            'parent.child',
        ];

        yield 'property alias collision' => [
            ContentSystemException::propertyAliasCollision('name', 'ctx1', 'ctx2'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__PROPERTY_ALIAS_COLLISION',
            'name',
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
                    new ConstraintViolation('too short', null, [], null, '[Sw:Bad:B].vendor', null),
                ])
            ),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__ELEMENT_TYPES_INVALID',
            '[Sw:Bad:A].label: must not be blank; [Sw:Bad:B].vendor: too short',
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
    }
}
