<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Converter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Api\Converter\ConverterRegistry;
use Shopware\Core\Framework\Api\Converter\DefaultApiConverter;
use Shopware\Core\Framework\Api\Converter\Exceptions\ApiConversionException;
use Shopware\Core\Framework\Api\Converter\Exceptions\QueryFutureEntityException;
use Shopware\Core\Framework\Api\Converter\Exceptions\QueryFutureFieldException;
use Shopware\Core\Framework\Api\Converter\Exceptions\QueryRemovedEntityException;
use Shopware\Core\Framework\Api\Converter\Exceptions\QueryRemovedFieldException;
use Shopware\Core\Framework\Api\Serializer\JsonApiEncoder;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\CompiledFieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Test\Api\Converter\fixtures\DeprecatedConverter;
use Shopware\Core\Framework\Test\Api\Converter\fixtures\DeprecatedDefinition;
use Shopware\Core\Framework\Test\Api\Converter\fixtures\DeprecatedEntity;
use Shopware\Core\Framework\Test\Api\Converter\fixtures\DeprecatedEntityDefinition;
use Shopware\Core\Framework\Test\Api\Converter\fixtures\NewEntityDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Tax\TaxEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ApiVersionConverterTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var ConverterRegistry
     */
    private $converterRegistry;

    /**
     * @var ApiVersionConverter
     */
    private $apiVersionConverter;

    public function setUp(): void
    {
        $converter = $this->createMock(DefaultApiConverter::class);
        $converter->method('isDeprecated')->willReturn(false);
        $converter->method('convert')->willReturnArgument(2);

        $this->converterRegistry = new ConverterRegistry(
            [
                new DeprecatedConverter(),
            ],
            $converter
        );

        $this->apiVersionConverter = new ApiVersionConverter(
            $this->converterRegistry,
            $this->getContainer()->get('request_stack')
        );
    }

    public function testNewFieldIsNotInOldResponseForJsonApi(): void
    {
        $jsonApiEncoder = new JsonApiEncoder($this->apiVersionConverter);

        $deprecatedDefinition = new DeprecatedDefinition();
        $deprecatedDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $result = $jsonApiEncoder->encode(
            new Criteria(),
            $deprecatedDefinition,
            $this->getDeprecatedEntity(),
            'http://localhost',
            1
        );
        $result = json_decode($result, true);

        static::assertEquals(10, $result['data']['attributes']['price']);
        static::assertArrayNotHasKey('prices', $result['data']['attributes']);
        static::assertArrayHasKey('tax', $result['data']['relationships']);
        static::assertCount(1, $result['included']);
        static::assertArrayHasKey('taxId', $result['data']['attributes']);
    }

    public function testDeprecatedFieldIsNotInNewResponseForJsonApi(): void
    {
        $jsonApiEncoder = new JsonApiEncoder($this->apiVersionConverter);

        $deprecatedDefinition = new DeprecatedDefinition();
        $deprecatedDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $result = $jsonApiEncoder->encode(
            new Criteria(),
            $deprecatedDefinition,
            $this->getDeprecatedEntity(),
            'http://localhost',
            2
        );
        $result = json_decode($result, true);

        static::assertEquals([10], $result['data']['attributes']['prices']);
        static::assertArrayNotHasKey('price', $result['data']['attributes']);
        static::assertArrayNotHasKey('tax', $result['data']['relationships']);
        static::assertCount(0, $result['included']);
        static::assertArrayNotHasKey('taxId', $result['data']['attributes']);
    }

    public function testNewFieldIsNotInOldResponseForJson(): void
    {
        $jsonEntityEncoder = new JsonEntityEncoder($this->getContainer()->get('serializer'), $this->apiVersionConverter);

        $deprecatedDefinition = new DeprecatedDefinition();
        $deprecatedDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $result = $jsonEntityEncoder->encode(
            new Criteria(),
            $deprecatedDefinition,
            $this->getDeprecatedEntity(),
            'http://localhost',
            1
        );

        static::assertEquals(10, $result['price']);
        static::assertArrayNotHasKey('prices', $result);
        static::assertArrayHasKey('tax', $result);
        static::assertArrayHasKey('taxId', $result);
    }

    public function testDeprecatedFieldIsNotInNewResponseForJson(): void
    {
        $jsonEntityEncoder = new JsonEntityEncoder($this->getContainer()->get('serializer'), $this->apiVersionConverter);

        $deprecatedDefinition = new DeprecatedDefinition();
        $deprecatedDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $result = $jsonEntityEncoder->encode(
            new Criteria(),
            $deprecatedDefinition,
            $this->getDeprecatedEntity(),
            'http://localhost',
            2
        );

        static::assertEquals([10], $result['prices']);
        static::assertArrayNotHasKey('price', $result);
        static::assertArrayNotHasKey('tax', $result);
        static::assertArrayNotHasKey('taxId', $result);
    }

    public function testCompatibilityHeaderIsChecked(): void
    {
        $registry = $this->createMock(ConverterRegistry::class);
        $registry->method('isDeprecated')->willReturn(true);
        $registry->method('isFromFuture')->willReturn(false);

        $requestStack = new RequestStack();
        $apiVersionConverter = new ApiVersionConverter($registry, $requestStack);

        $request = new Request([], [], [], [], [], [], []);
        $request->headers->set(PlatformRequest::HEADER_IGNORE_DEPRECATIONS, 'true');
        $requestStack->push($request);

        $isAllowed = $apiVersionConverter->isAllowed('foo', 'bar', 1);
        static::assertTrue($isAllowed);

        $definition = $this->createMock(EntityDefinition::class);

        $class = new \ReflectionClass(EntityDefinition::class);
        $property = $class->getProperty('fields');
        $property->setAccessible(true);
        $property->setValue(
            $definition,
            new CompiledFieldCollection(
                $this->createMock(DefinitionInstanceRegistry::class),
                [new StringField('bar', 'bar')]
            )
        );

        $definition->method('getEntityName')->willReturn('foo');

        $conversionException = new ApiConversionException();
        $apiVersionConverter->convertPayload($definition, ['bar' => 'asdf'], 1, $conversionException);

        static::assertSame([], iterator_to_array($conversionException->getErrors()));
    }

    public function testTryingToWriteFieldFromFutureLeadsToException(): void
    {
        $payload = [
            'id' => Uuid::randomHex(),
            'price' => 10,
            'taxId' => Uuid::randomHex(),
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => '19%',
                'taxRate' => 19,
            ],
            'prices' => [
                10,
            ],
        ];

        $deprecatedDefinition = new DeprecatedDefinition();
        $deprecatedDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $conversionException = new ApiConversionException();
        $this->apiVersionConverter->convertPayload($deprecatedDefinition, $payload, 1, $conversionException);

        static::assertCount(1, $conversionException->getErrors());
        $error = $conversionException->getErrors()->current();
        static::assertEquals('FRAMEWORK__WRITE_FUTURE_FIELD', $error['code']);
        static::assertEquals('/prices', $error['source']['pointer']);
    }

    public function testTryingToWriteDeprecatedFieldLeadsToException(): void
    {
        $payload = [
            'id' => Uuid::randomHex(),
            'price' => 10,
            'taxId' => Uuid::randomHex(),
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => '19%',
                'taxRate' => 19,
            ],
            'prices' => [
                10,
            ],
        ];

        $deprecatedDefinition = new DeprecatedDefinition();
        $deprecatedDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $conversionException = new ApiConversionException();
        $this->apiVersionConverter->convertPayload($deprecatedDefinition, $payload, 2, $conversionException);

        $errors = iterator_to_array($conversionException->getErrors());
        static::assertCount(3, $errors);

        static::assertEquals('FRAMEWORK__WRITE_REMOVED_FIELD', $errors[0]['code']);
        static::assertEquals('/price', $errors[0]['source']['pointer']);

        static::assertEquals('FRAMEWORK__WRITE_REMOVED_FIELD', $errors[1]['code']);
        static::assertEquals('/taxId', $errors[1]['source']['pointer']);

        static::assertEquals('FRAMEWORK__WRITE_REMOVED_FIELD', $errors[2]['code']);
        static::assertEquals('/tax', $errors[2]['source']['pointer']);
    }

    public function testItConvertsDeprecatedFieldToNewField(): void
    {
        $payload = [
            'id' => Uuid::randomHex(),
            'price' => 10,
            'taxId' => Uuid::randomHex(),
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => '19%',
                'taxRate' => 19,
            ],
        ];

        $deprecatedDefinition = new DeprecatedDefinition();
        $deprecatedDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $conversionException = new ApiConversionException();
        $payload = $this->apiVersionConverter->convertPayload($deprecatedDefinition, $payload, 1, $conversionException);

        static::assertCount(0, $conversionException->getErrors());

        static::assertArrayNotHasKey('price', $payload);
        static::assertEquals([10], $payload['prices']);
    }

    public function testDeprecatedPathIsRequestedLeadsToException(): void
    {
        $deprecatedDefinition = new DeprecatedDefinition();
        $deprecatedDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        // deprecated/{id}/tax
        $entityPath = [
            [
                'entity' => 'deprecated',
                'value' => Uuid::randomHex(),
                'definition' => $deprecatedDefinition,
                'field' => null,
            ],
            [
                'entity' => 'tax',
                'value' => null,
                'definition' => $this->getContainer()->get(TaxDefinition::class),
                'field' => $deprecatedDefinition->getField('tax'),
            ],
        ];

        $this->expectException(QueryRemovedFieldException::class);
        $this->apiVersionConverter->validateEntityPath($entityPath, 2);
    }

    public function testDeprecatedEntityPathIsRequestedLeadsToException(): void
    {
        $deprecatedEntityDefinition = new DeprecatedEntityDefinition();
        $deprecatedEntityDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        // path = "deprecated-entity"
        $entityPath = [
            [
                'entity' => 'deprecated_entity',
                'value' => null,
                'definition' => $deprecatedEntityDefinition,
                'field' => null,
            ],
        ];

        $this->expectException(QueryRemovedEntityException::class);
        $this->apiVersionConverter->validateEntityPath($entityPath, 2);
    }

    public function testFuturePathIsRequestedLeadsToException(): void
    {
        $deprecatedDefinition = new DeprecatedDefinition();
        $deprecatedDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        // deprecated/{id}/product
        $entityPath = [
            [
                'entity' => 'deprecated',
                'value' => Uuid::randomHex(),
                'definition' => $deprecatedDefinition,
                'field' => null,
            ],
            [
                'entity' => 'product',
                'value' => null,
                'definition' => $this->getContainer()->get(ProductDefinition::class),
                'field' => $deprecatedDefinition->getField('product'),
            ],
        ];

        $this->expectException(QueryFutureFieldException::class);
        $this->apiVersionConverter->validateEntityPath($entityPath, 1);
    }

    public function testNewEntityPathIsRequestedLeadsToException(): void
    {
        $newEntityDefinition = new NewEntityDefinition();
        $newEntityDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        // path = "new-entity"
        $entityPath = [
            [
                'entity' => 'new_entity',
                'value' => null,
                'definition' => $newEntityDefinition,
                'field' => null,
            ],
        ];

        $this->expectException(QueryFutureEntityException::class);
        $this->apiVersionConverter->validateEntityPath($entityPath, 1);
    }

    public function testCriteriaWithDeprecatedFieldThrowsException(): void
    {
        $deprecatedDefinition = new DeprecatedDefinition();
        $deprecatedDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $searchCriteriaBuilder = new RequestCriteriaBuilder(
            new AggregationParser(),
            $this->apiVersionConverter,
            $this->getContainer()->getParameter('shopware.api.max_limit')
        );

        $query = [
            'filter' => [
                ['type' => 'equals', 'field' => 'price', 'value' => '10'],
            ],
            'grouping' => [
                'taxId',
            ],
            'sort' => [[
                'field' => 'tax',
                'order' => 'desc',
            ]],
        ];

        $request = new Request($query, [], ['version' => 2]);

        $exception = null;

        try {
            $searchCriteriaBuilder->handleRequest($request, new Criteria(), $deprecatedDefinition, Context::createDefaultContext());
        } catch (SearchRequestException $e) {
            $exception = $e;
        }

        static::assertInstanceOf(SearchRequestException::class, $exception);

        $errors = iterator_to_array($exception->getErrors());
        static::assertCount(3, $errors);

        static::assertEquals('FRAMEWORK__QUERY_REMOVED_FIELD', $errors[0]['code']);
        static::assertEquals('/price', $errors[0]['source']['pointer']);

        static::assertEquals('FRAMEWORK__QUERY_REMOVED_FIELD', $errors[1]['code']);
        static::assertEquals('/tax', $errors[1]['source']['pointer']);

        static::assertEquals('FRAMEWORK__QUERY_REMOVED_FIELD', $errors[2]['code']);
        static::assertEquals('/taxId', $errors[2]['source']['pointer']);
    }

    public function testCriteriaWithFutureFieldThrowsException(): void
    {
        $deprecatedDefinition = new DeprecatedDefinition();
        $deprecatedDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $searchCriteriaBuilder = new RequestCriteriaBuilder(
            new AggregationParser(),
            $this->apiVersionConverter,
            $this->getContainer()->getParameter('shopware.api.max_limit')
        );

        $query = [
            'filter' => [
                ['type' => 'equals', 'field' => 'prices', 'value' => '10'],
            ],
            'grouping' => [
                'productId',
            ],
            'sort' => [[
                'field' => 'product',
                'order' => 'desc',
            ]],
        ];

        $request = new Request($query, [], ['version' => 1]);

        $exception = null;

        try {
            $searchCriteriaBuilder->handleRequest($request, new Criteria(), $deprecatedDefinition, Context::createDefaultContext());
        } catch (SearchRequestException $e) {
            $exception = $e;
        }

        static::assertInstanceOf(SearchRequestException::class, $exception);

        $errors = iterator_to_array($exception->getErrors());
        static::assertCount(3, $errors);

        static::assertEquals('FRAMEWORK__QUERY_FUTURE_FIELD', $errors[0]['code']);
        static::assertEquals('/prices', $errors[0]['source']['pointer']);

        static::assertEquals('FRAMEWORK__QUERY_FUTURE_FIELD', $errors[1]['code']);
        static::assertEquals('/product', $errors[1]['source']['pointer']);

        static::assertEquals('FRAMEWORK__QUERY_FUTURE_FIELD', $errors[2]['code']);
        static::assertEquals('/productId', $errors[2]['source']['pointer']);
    }

    public function testConvertEntityStripsDeprecatedFields(): void
    {
        $deprecatedDefinition = new DeprecatedDefinition();
        $deprecatedDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $id = Uuid::randomHex();
        $converted = $this->apiVersionConverter->convertEntity($deprecatedDefinition, $this->getDeprecatedEntity($id), 2);

        static::assertIsArray($converted);
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('prices', $converted);
        static::assertArrayHasKey('product', $converted);
        static::assertArrayHasKey('productId', $converted);

        static::assertArrayNotHasKey('price', $converted);
        static::assertArrayNotHasKey('tax', $converted);
        static::assertArrayNotHasKey('taxId', $converted);
    }

    public function testConvertEntityStripsFutureFields(): void
    {
        $deprecatedDefinition = new DeprecatedDefinition();
        $deprecatedDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $id = Uuid::randomHex();
        $converted = $this->apiVersionConverter->convertEntity($deprecatedDefinition, $this->getDeprecatedEntity($id), 1);

        static::assertIsArray($converted);
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('price', $converted);
        static::assertArrayHasKey('tax', $converted);
        static::assertArrayHasKey('taxId', $converted);

        static::assertArrayNotHasKey('prices', $converted);
        static::assertArrayNotHasKey('product', $converted);
        static::assertArrayNotHasKey('productId', $converted);
    }

    private function getDeprecatedEntity(?string $id = null): DeprecatedEntity
    {
        $entity = new DeprecatedEntity();
        $entity->setId($id ?? Uuid::randomHex());
        $entity->setPrice(10);
        $entity->setPrices([10]);

        $tax = new TaxEntity();
        $tax->setId(Uuid::randomHex());
        $tax->setName('19%');
        $tax->setTaxRate(19);
        $tax->internalSetEntityName('tax');

        $entity->setTax($tax);
        $entity->setTaxId($tax->getId());

        return $entity;
    }
}
