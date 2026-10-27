<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class DefaultMappingCreationTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    private const COMPONENT = 'Sw:Product:PriceDisplay';

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('draftCreationPaths')]
    public function testDraftScaffoldsReceiveTheDefaultMapping(string $action, array $payload): void
    {
        $body = $this->mutate($action, ['rootSource' => 'product', ...$payload]);
        $element = $action === 'insert-preset'
            ? $body['layout'][0]['slots']['content'][1]
            : $body['layout'][0];

        static::assertSame(self::COMPONENT, $element['component']);
        static::assertSame(['type' => 'root', 'id' => 'product'], $element['acceptsContext']['product']['source']);
        static::assertArrayHasKey('product', $element['dataRequirements']);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function draftCreationPaths(): iterable
    {
        yield 'fresh insertion' => ['insert-element', ['layout' => [], 'type' => self::COMPONENT]];
        yield 'preset insertion seeds nested elements' => ['insert-preset', ['layout' => [], 'presetId' => 'Sw:ProductDetailPage']];
        yield 'replacement creates a fresh scaffold' => ['replace-element', [
            'layout' => [['id' => 'old-element', 'component' => 'Sw:Grid:Container']],
            'elementId' => 'old-element',
            'newType' => self::COMPONENT,
        ]];
    }

    #[DataProvider('directWritePaths')]
    public function testDirectCreationSeedsStoredDefaultsWithoutInventingMappings(bool $sync): void
    {
        $id = Uuid::randomHex();
        $payload = $this->layoutPayload($id, [['id' => 'product-price', 'component' => self::COMPONENT]]);

        if ($sync) {
            $this->getBrowser()->jsonRequest('POST', '/api/_action/sync', [[
                'action' => 'upsert',
                'entity' => 'content_layout',
                'payload' => [$payload],
            ]]);
            $response = $this->getBrowser()->getResponse();
            static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        } else {
            $this->repository()->create([$payload], Context::createDefaultContext());
        }

        $element = $this->reload($id)->getLayout()[0];
        static::assertSame([], $element->contextDefinitions->getAllConsumers());
        static::assertTrue($element->property('showTaxInfo')?->asBool());
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function directWritePaths(): iterable
    {
        yield 'plain DAL creation' => [false];
        yield 'Sync API creation' => [true];
    }

    public function testPersistedInsertionSeedsOnceAndUnmappingSurvivesSaveAndReload(): void
    {
        $id = Uuid::randomHex();
        $this->repository()->create([$this->layoutPayload($id, [[
            'id' => 'container',
            'component' => 'Sw:Grid:Container',
        ]])], Context::createDefaultContext());

        $body = $this->mutate($id . '/insert-element', ['type' => self::COMPONENT]);
        $mapped = $this->reload($id);
        static::assertSame(
            ['type' => 'root', 'id' => 'product'],
            $mapped->getLayout()[1]->contextDefinitions->getAllConsumers()['product']->source?->jsonSerialize(),
        );

        $this->mutate($id . '/unmap-property', [
            'elementId' => $body['layout'][1]['id'],
            'propertyKey' => 'product',
            'expectedVersion' => $mapped->getUpdatedAt()?->format(\DateTimeInterface::RFC3339_EXTENDED),
        ]);
        $unmapped = $this->reload($id);
        static::assertSame([], $unmapped->getLayout()[1]->contextDefinitions->getAllConsumers());
        static::assertArrayHasKey('product', $unmapped->getLayout()[1]->dataRequirements);

        $this->repository()->update([['id' => $id, 'layout' => $unmapped->getLayout()]], Context::createDefaultContext());

        $saved = $this->reload($id)->getLayout()[1];
        static::assertSame([], $saved->contextDefinitions->getAllConsumers());
        static::assertEquals($unmapped->getLayout()[1]->dataRequirements, $saved->dataRequirements);
    }

    #[DataProvider('copiedMappingStates')]
    public function testDuplicationPreservesMappingStateThroughSaveAndReload(bool $mapped): void
    {
        $body = $this->mutate('insert-element', ['layout' => [], 'rootSource' => 'product', 'type' => self::COMPONENT]);
        $elementId = $body['layout'][0]['id'];
        if (!$mapped) {
            $body = $this->mutate('unmap-property', [
                'layout' => $body['layout'],
                'rootSource' => 'product',
                'elementId' => $elementId,
                'propertyKey' => 'product',
            ]);
        }
        $originalConsumers = $body['layout'][0]['acceptsContext'];

        $duplicated = $this->mutate('duplicate-element', [
            'layout' => $body['layout'],
            'rootSource' => 'product',
            'elementId' => $elementId,
        ]);

        static::assertCount(2, $duplicated['layout']);
        foreach ($duplicated['layout'] as $element) {
            static::assertSame($originalConsumers, $element['acceptsContext']);
        }
        $id = Uuid::randomHex();
        $this->repository()->create([$this->layoutPayload($id, $duplicated['layout'])], Context::createDefaultContext());

        $stored = $this->reload($id)->getLayout();
        static::assertCount(2, $stored);
        foreach ($stored as $element) {
            $source = $element->contextDefinitions->getAllConsumers()['product']->source ?? null;
            static::assertSame($mapped ? ['type' => 'root', 'id' => 'product'] : null, $source?->jsonSerialize());
        }
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function copiedMappingStates(): iterable
    {
        yield 'default mapping is copied' => [true];
        yield 'deliberate unmapping is copied' => [false];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function mutate(string $action, array $payload): array
    {
        $this->getBrowser()->jsonRequest('POST', '/api/_action/content-system/layout/' . $action, $payload);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array<string, mixed>> $tree
     *
     * @return array<string, mixed>
     */
    private function layoutPayload(string $id, array $tree): array
    {
        return ['id' => $id, 'name' => 'Default mapping creation', 'version' => '1.0.0', 'rootSource' => 'product', 'layout' => $tree];
    }

    private function reload(string $id): ContentLayoutEntity
    {
        $layout = $this->repository()->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);

        return $layout;
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function repository(): EntityRepository
    {
        return static::getContainer()->get('content_layout.repository');
    }
}
