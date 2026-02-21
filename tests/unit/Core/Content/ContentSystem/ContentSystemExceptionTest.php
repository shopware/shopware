<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ContentSystemException::class)]
class ContentSystemExceptionTest extends TestCase
{
    /**
     * @param \Closure(): ContentSystemException $factory
     * @param array<string, mixed> $expectedParameters
     */
    #[DataProvider('exceptionFactoryProvider')]
    #[TestDox('$_dataName returns correct status, error code, parameters, and message')]
    public function testExceptionFactory(
        \Closure $factory,
        int $expectedStatus,
        string $expectedErrorCode,
        string $expectedMessage,
        array $expectedParameters,
    ): void {
        $exception = $factory();

        static::assertSame($expectedStatus, $exception->getStatusCode());
        static::assertSame($expectedErrorCode, $exception->getErrorCode());
        static::assertSame($expectedMessage, $exception->getMessage());
        static::assertSame($expectedParameters, $exception->getParameters());
    }

    /**
     * @return \Generator<string, array{
     *     \Closure(): ContentSystemException,
     *     int,
     *     string,
     *     string,
     *     array<string, mixed>
     * }>
     */
    public static function exceptionFactoryProvider(): \Generator
    {
        yield 'dataLoaderNotRegistered' => [
            static fn () => ContentSystemException::dataLoaderNotRegistered('product', 'image', 'element-123'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ContentSystemException::DATA_LOADER_NOT_REGISTERED,
            'Data loader for requirement type "product" not registered. Element type: "image", element ID: "element-123"',
            ['requirementType' => 'product', 'elementType' => 'image', 'elementId' => 'element-123'],
        ];

        yield 'configSerializerNotRegistered' => [
            static fn () => ContentSystemException::configSerializerNotRegistered('my-source'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ContentSystemException::CONFIG_SERIALIZER_NOT_REGISTERED,
            'Config serializer for source "my-source" is not registered',
            ['source' => 'my-source'],
        ];

        yield 'invalidFieldType' => [
            static fn () => ContentSystemException::invalidFieldType('ExpectedClass', 'ActualClass'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ContentSystemException::INVALID_FIELD_TYPE,
            'Expected field of type ExpectedClass, got ActualClass',
            ['expectedClass' => 'ExpectedClass', 'actualClass' => 'ActualClass'],
        ];

        yield 'invalidFieldValueType' => [
            static fn () => ContentSystemException::invalidFieldValueType('myField', 'string', 'int'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ContentSystemException::INVALID_FIELD_VALUE_TYPE,
            'Field myField expected string, got int',
            ['fieldName' => 'myField', 'expectedType' => 'string', 'actualType' => 'int'],
        ];

        yield 'invalidMapKey' => [
            static fn () => ContentSystemException::invalidMapKey('MyMap', 'integer'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ContentSystemException::INVALID_MAP_KEY,
            'MyMap key must be string, got integer',
            ['mapType' => 'MyMap', 'actualType' => 'integer'],
        ];

        yield 'invalidMapValue' => [
            static fn () => ContentSystemException::invalidMapValue('MyMap', 'some-key', 'string', 'array'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ContentSystemException::INVALID_MAP_VALUE,
            'MyMap value for "some-key" must be string, got array',
            ['mapType' => 'MyMap', 'key' => 'some-key', 'expectedType' => 'string', 'actualType' => 'array'],
        ];

        yield 'layoutAssignmentNotFound' => [
            static fn () => ContentSystemException::layoutAssignmentNotFound('product', 'prod-abc', 'sc-xyz'),
            Response::HTTP_NOT_FOUND,
            ContentSystemException::LAYOUT_ASSIGNMENT_NOT_FOUND,
            'No layout assignment found for product "prod-abc" in sales channel "sc-xyz"',
            ['entityType' => 'product', 'entityId' => 'prod-abc', 'salesChannelId' => 'sc-xyz'],
        ];

        yield 'layoutNotFound' => [
            static fn () => ContentSystemException::layoutNotFound('layout-id-42'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ContentSystemException::LAYOUT_NOT_FOUND,
            'Content layout with ID "layout-id-42" does not exist. This indicates a configuration error.',
            ['layoutId' => 'layout-id-42'],
        ];

        yield 'elementNotFound' => [
            static fn () => ContentSystemException::elementNotFound('elem-99'),
            Response::HTTP_NOT_FOUND,
            ContentSystemException::ELEMENT_NOT_FOUND,
            'Element with ID "elem-99" not found in layout',
            ['elementId' => 'elem-99'],
        ];

        yield 'pathIntegrityViolation' => [
            static fn () => ContentSystemException::pathIntegrityViolation('circular reference detected'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ContentSystemException::PATH_INTEGRITY_VIOLATION,
            'Path integrity violation: circular reference detected',
            ['reason' => 'circular reference detected'],
        ];

        yield 'criteriaFilterFieldDecodeNotSupported' => [
            static fn () => ContentSystemException::criteriaFilterFieldDecodeNotSupported(),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ContentSystemException::CRITERIA_FILTER_FIELD_DECODE_NOT_SUPPORTED,
            'CriteriaFilterField does not support decode. Use ResolutionConfigField for full encode/decode support with entity context.',
            [],
        ];

        yield 'noFactoryCanHandle' => [
            static fn () => ContentSystemException::noFactoryCanHandle('/some/path'),
            Response::HTTP_NOT_FOUND,
            ContentSystemException::NO_FACTORY_CAN_HANDLE,
            'No context factory can handle the request for path "/some/path"',
            ['path' => '/some/path'],
        ];

        yield 'invalidEntityPath' => [
            static fn () => ContentSystemException::invalidEntityPath('product', 'bad/path', 'entity/id'),
            Response::HTTP_BAD_REQUEST,
            ContentSystemException::INVALID_ENTITY_PATH,
            'Invalid product path format: "bad/path". Expected format: entity/id',
            ['entityType' => 'product', 'path' => 'bad/path', 'expectedFormat' => 'entity/id'],
        ];

        yield 'redistributeWithDottedPath' => [
            static fn () => ContentSystemException::redistributeWithDottedPath('some.dotted.key'),
            Response::HTTP_BAD_REQUEST,
            ContentSystemException::REDISTRIBUTE_DOTTED_PATH,
            'Context key "some.dotted.key" uses dot notation and cannot be redistributed. Only base keys support redistribution.',
            ['key' => 'some.dotted.key'],
        ];

        yield 'redistributeConflict' => [
            static fn () => ContentSystemException::redistributeConflict('conflicting-key'),
            Response::HTTP_BAD_REQUEST,
            ContentSystemException::REDISTRIBUTE_CONFLICT,
            'Context key "conflicting-key" has both redistribute:true and explicit provides_context. Use one or the other.',
            ['key' => 'conflicting-key'],
        ];

        yield 'consumerAliasWithoutRedistribute' => [
            static fn () => ContentSystemException::consumerAliasWithoutRedistribute('alias-key'),
            Response::HTTP_BAD_REQUEST,
            ContentSystemException::CONSUMER_ALIAS_WITHOUT_REDISTRIBUTE,
            'Context key "alias-key" has consumer_alias but redistribute is not true. consumer_alias requires redistribute:true.',
            ['key' => 'alias-key'],
        ];

        yield 'contextPathNotResolvable without reason' => [
            static fn () => ContentSystemException::contextPathNotResolvable('root.child', 'elem-55'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ContentSystemException::CONTEXT_PATH_NOT_RESOLVABLE,
            'Cannot resolve context path "root.child" for element "elem-55"',
            ['fullPath' => 'root.child', 'elementId' => 'elem-55', 'reason' => null],
        ];

        yield 'contextPathNotResolvable with reason' => [
            static fn () => ContentSystemException::contextPathNotResolvable('root.child', 'elem-55', 'key missing'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ContentSystemException::CONTEXT_PATH_NOT_RESOLVABLE,
            'Cannot resolve context path "root.child" for element "elem-55": key missing',
            ['fullPath' => 'root.child', 'elementId' => 'elem-55', 'reason' => 'key missing'],
        ];

        yield 'propertyAliasWithDotNotation' => [
            static fn () => ContentSystemException::propertyAliasWithDotNotation('ctx-key', 'alias.with.dots'),
            Response::HTTP_BAD_REQUEST,
            ContentSystemException::PROPERTY_ALIAS_WITH_DOT_NOTATION,
            'Context key "ctx-key" has property_alias "alias.with.dots" with dot notation. Property aliases must be simple property names without dots.',
            ['key' => 'ctx-key', 'alias' => 'alias.with.dots'],
        ];

        yield 'propertyAliasCollision' => [
            static fn () => ContentSystemException::propertyAliasCollision('prop-key', 'first-ctx', 'second-ctx'),
            Response::HTTP_BAD_REQUEST,
            ContentSystemException::PROPERTY_ALIAS_COLLISION,
            'Property key "prop-key" is used by both context "first-ctx" and "second-ctx". Each property_alias must be unique within an element.',
            ['propertyKey' => 'prop-key', 'firstContext' => 'first-ctx', 'secondContext' => 'second-ctx'],
        ];

        yield 'routesAlreadyLoaded' => [
            static fn () => ContentSystemException::routesAlreadyLoaded(),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ContentSystemException::ROUTES_ALREADY_LOADED,
            'Content system routes are already loaded.',
            [],
        ];
    }
}
