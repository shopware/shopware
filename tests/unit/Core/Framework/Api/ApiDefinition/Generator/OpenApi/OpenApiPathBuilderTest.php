<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\PathItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiPathBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SalesChannelSimpleDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SimpleDefinition;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OpenApiPathBuilder::class)]
class OpenApiPathBuilderTest extends TestCase
{
    private OpenApiPathBuilder $pathBuilder;

    private StaticDefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->pathBuilder = new OpenApiPathBuilder();
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [
                SimpleDefinition::class,
                SalesChannelSimpleDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testCreatePathItemsCoversAllCrudOperations(): void
    {
        $pathItems = $this->pathBuilder->createPathItems($this->definitionRegistry->get(SimpleDefinition::class), '/simple');

        static::assertSame(['/simple', '/search/simple', '/simple/{id}', '/aggregate/simple'], array_keys($pathItems));
        static::assertSame(['get', 'post'], array_keys($pathItems['/simple']));
        static::assertSame(['post'], array_keys($pathItems['/search/simple']));
        static::assertSame(['get', 'delete', 'patch'], array_keys($pathItems['/simple/{id}']));
        static::assertSame(['post'], array_keys($pathItems['/aggregate/simple']));

        $listing = $pathItems['/simple']['get'];
        static::assertSame(['tags', 'summary', 'description', 'operationId', 'parameters', 'responses'], array_keys($listing));
        static::assertArrayHasKey('parameters', $listing);
        static::assertSame(['Simple'], $listing['tags']);
        static::assertSame('getSimpleList', $listing['operationId']);
        static::assertSame('Available since: 6.0.0.0', $listing['description']);
        static::assertSame(['limit', 'page', 'query'], array_column($listing['parameters'], 'name'));
        static::assertSame(['$ref' => '#/components/responses/401'], $listing['responses'][401]);
        static::assertSame(
            ['$ref' => '#/components/schemas/Simple'],
            $listing['responses'][200]['content']['application/json']['schema']['properties']['data']['items']
        );

        $detail = $pathItems['/simple/{id}']['get'];
        static::assertArrayHasKey('parameters', $detail);
        static::assertSame('getSimple', $detail['operationId']);
        static::assertSame('id', $detail['parameters'][0]['name']);
        static::assertTrue($detail['parameters'][0]['required']);
        static::assertSame('Detail of Simple', $detail['responses'][200]['description']);

        $update = $pathItems['/simple/{id}']['patch'];
        static::assertArrayHasKey('parameters', $update);
        static::assertArrayHasKey('requestBody', $update);
        static::assertSame('updateSimple', $update['operationId']);
        static::assertSame(['id', '_response'], array_column($update['parameters'], 'name'));
        static::assertSame(['$ref' => '#/components/schemas/Simple'], $update['requestBody']['content']['application/json']['schema']);

        static::assertSame(['$ref' => '#/components/responses/204'], $pathItems['/simple/{id}']['delete']['responses'][204]);
        static::assertSame('searchSimple', $pathItems['/search/simple']['post']['operationId']);
        static::assertSame('Available since: 6.6.10.0', $pathItems['/aggregate/simple']['post']['description']);
    }

    public function testCreatePathItemsOmitsWriteOperationsForSalesChannelDefinitions(): void
    {
        $pathItems = $this->pathBuilder->createPathItems($this->definitionRegistry->get(SalesChannelSimpleDefinition::class), '/simple');

        static::assertSame(['get'], array_keys($pathItems['/simple']));
        static::assertSame(['get'], array_keys($pathItems['/simple/{id}']));
        static::assertSame(['post'], array_keys($pathItems['/search/simple']));
        static::assertSame(['post'], array_keys($pathItems['/aggregate/simple']));
    }

    public function testCreateTag(): void
    {
        static::assertSame(
            ['name' => 'Simple', 'description' => 'The endpoint for operations on Simple'],
            $this->pathBuilder->createTag($this->definitionRegistry->get(SimpleDefinition::class))
        );
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with OpenApiPathBuilder::getPathActions()
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetPathActionsSerializesToTheCreatedPathItems(): void
    {
        $definition = $this->definitionRegistry->get(SimpleDefinition::class);

        $pathActions = $this->pathBuilder->getPathActions($definition, '/simple');
        $pathItems = $this->pathBuilder->createPathItems($definition, '/simple');

        static::assertSame(array_keys($pathItems), array_keys($pathActions));
        foreach ($pathItems as $path => $pathItem) {
            static::assertInstanceOf(PathItem::class, $pathActions[$path]);
            static::assertSame($path, $pathActions[$path]->path);
            static::assertSame(['path' => $path] + $pathItem, json_decode($pathActions[$path]->toJson(), true, flags: \JSON_THROW_ON_ERROR));
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with OpenApiPathBuilder::getTag()
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetTagSerializesToTheCreatedTag(): void
    {
        $definition = $this->definitionRegistry->get(SimpleDefinition::class);

        static::assertSame(
            $this->pathBuilder->createTag($definition),
            json_decode($this->pathBuilder->getTag($definition)->toJson(), true, flags: \JSON_THROW_ON_ERROR)
        );
    }
}
