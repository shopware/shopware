<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Symfony\Component\HttpFoundation\Response;

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

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame('CONTENT_SYSTEM__ELEMENT_TYPE_LOAD_FAILED', $e->getErrorCode());
        static::assertStringContainsString('test.yaml', $e->getMessage());
        static::assertSame($previous, $e->getPrevious());
    }

    /**
     * @return iterable<string, array{ContentSystemException, int, string, string}>
     */
    public static function producesCorrectStatusAndErrorCodeProvider(): iterable
    {
        yield 'dataLoaderNotRegistered' => [
            ContentSystemException::dataLoaderNotRegistered('product', 'Sw:Product:Card', 'elem-1'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__DATA_LOADER_NOT_REGISTERED',
            'product',
        ];

        yield 'configSerializerNotRegistered' => [
            ContentSystemException::configSerializerNotRegistered('yaml'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__CONFIG_SERIALIZER_NOT_REGISTERED',
            'yaml',
        ];

        yield 'invalidFieldType' => [
            ContentSystemException::invalidFieldType('StringField', 'IntField'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__INVALID_FIELD_TYPE',
            'StringField',
        ];

        yield 'invalidFieldValueType' => [
            ContentSystemException::invalidFieldValueType('count', 'int', 'string'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__INVALID_FIELD_VALUE_TYPE',
            'count',
        ];

        yield 'invalidMapKey' => [
            ContentSystemException::invalidMapKey('ConfigMap', 'integer'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__INVALID_MAP_KEY',
            'ConfigMap',
        ];

        yield 'invalidMapValue' => [
            ContentSystemException::invalidMapValue('ConfigMap', 'foo', 'string', 'array'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__INVALID_MAP_VALUE',
            'foo',
        ];

        yield 'layoutAssignmentNotFound' => [
            ContentSystemException::layoutAssignmentNotFound('product', 'prod-1', 'sc-1'),
            Response::HTTP_NOT_FOUND,
            'CONTENT_SYSTEM__LAYOUT_ASSIGNMENT_NOT_FOUND',
            'prod-1',
        ];

        yield 'layoutNotFound' => [
            ContentSystemException::layoutNotFound('layout-1'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__LAYOUT_NOT_FOUND',
            'layout-1',
        ];

        yield 'elementNotFound' => [
            ContentSystemException::elementNotFound('elem-1'),
            Response::HTTP_NOT_FOUND,
            'CONTENT_SYSTEM__ELEMENT_NOT_FOUND',
            'elem-1',
        ];

        yield 'pathIntegrityViolation' => [
            ContentSystemException::pathIntegrityViolation('duplicate key'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__PATH_INTEGRITY_VIOLATION',
            'duplicate key',
        ];

        yield 'noFactoryCanHandle' => [
            ContentSystemException::noFactoryCanHandle('/store-api/content/unknown'),
            Response::HTTP_NOT_FOUND,
            'CONTENT_SYSTEM__NO_FACTORY_CAN_HANDLE',
            '/store-api/content/unknown',
        ];

        yield 'invalidEntityPath' => [
            ContentSystemException::invalidEntityPath('product', 'bad//path', 'entity/id'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__INVALID_ENTITY_PATH',
            'bad//path',
        ];

        yield 'redistributeWithDottedPath' => [
            ContentSystemException::redistributeWithDottedPath('parent.child'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__REDISTRIBUTE_DOTTED_PATH',
            'parent.child',
        ];

        yield 'redistributeConflict' => [
            ContentSystemException::redistributeConflict('myKey'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__REDISTRIBUTE_CONFLICT',
            'myKey',
        ];

        yield 'consumerAliasWithoutRedistribute' => [
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

        yield 'propertyAliasWithDotNotation' => [
            ContentSystemException::propertyAliasWithDotNotation('myKey', 'parent.child'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__PROPERTY_ALIAS_WITH_DOT_NOTATION',
            'parent.child',
        ];

        yield 'propertyAliasCollision' => [
            ContentSystemException::propertyAliasCollision('name', 'ctx1', 'ctx2'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__PROPERTY_ALIAS_COLLISION',
            'name',
        ];

        yield 'missingExtendsAnnotation' => [
            ContentSystemException::missingExtendsAnnotation('App\Loader\MyLoader'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__MISSING_EXTENDS_ANNOTATION',
            'App\Loader\MyLoader',
        ];

        yield 'unsupportedTypeNode' => [
            ContentSystemException::unsupportedTypeNode('UnionTypeNode'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__UNSUPPORTED_TYPE_NODE',
            'UnionTypeNode',
        ];

        yield 'unresolvableTypeClass' => [
            ContentSystemException::unresolvableTypeClass('SomeClass', 'App\Loader\MyLoader'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__UNRESOLVABLE_TYPE_CLASS',
            'SomeClass',
        ];

        yield 'routesAlreadyLoaded' => [
            ContentSystemException::routesAlreadyLoaded(),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__ROUTES_ALREADY_LOADED',
            'already loaded',
        ];

        yield 'elementTypeDuplicate' => [
            ContentSystemException::elementTypeDuplicate('Sw:Product:Card', 'core', 'MyPlugin'),
            Response::HTTP_CONFLICT,
            'CONTENT_SYSTEM__ELEMENT_TYPE_DUPLICATE',
            'Sw:Product:Card',
        ];

        yield 'elementTypeInvalid' => [
            ContentSystemException::elementTypeInvalid('Sw:Bad:Type', 'missing field'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__ELEMENT_TYPE_INVALID',
            'Sw:Bad:Type',
        ];

        yield 'elementTypeMissingRequiredField' => [
            ContentSystemException::elementTypeMissingRequiredField('meta.name'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT_SYSTEM__ELEMENT_TYPE_MISSING_REQUIRED_FIELD',
            'meta.name',
        ];

        yield 'elementTypeNotFound' => [
            ContentSystemException::elementTypeNotFound('Sw:Unknown:Type'),
            Response::HTTP_NOT_FOUND,
            'CONTENT_SYSTEM__ELEMENT_TYPE_NOT_FOUND',
            'Sw:Unknown:Type',
        ];

        yield 'elementTypeUnregistered' => [
            ContentSystemException::elementTypeUnregistered('Sw:Ghost:Type'),
            Response::HTTP_BAD_REQUEST,
            'CONTENT_SYSTEM__ELEMENT_TYPE_UNREGISTERED',
            'Sw:Ghost:Type',
        ];
    }
}
