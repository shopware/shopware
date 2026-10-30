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
class AttachmentMappingStateTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    #[DataProvider('attachmentStateProvider')]
    public function testAttachmentPreservesAuthoredState(bool $persisted, bool $nested, bool $mapped, bool $selectedProduct): void
    {
        $body = $this->mutate('insert-element', [
            'rootSource' => 'product',
            'type' => 'Sw:Product:PriceDisplay',
        ]);
        if (!$mapped) {
            $body = $this->mutate('unmap-property', [
                'rootSource' => 'product',
                'layout' => $body['layout'],
                'elementId' => $body['layout'][0]['id'],
                'propertyKey' => 'product',
            ]);
        }
        $source = $body['layout'][0];
        $source['properties']['showTaxInfo'] = false;
        if ($selectedProduct) {
            $source['properties']['productId'] = Uuid::randomHex();
        }
        $incoming = $source;
        if ($nested) {
            $incoming = [
                'id' => 'copied-container',
                'component' => 'Sw:Grid:Container',
                'slots' => ['content' => [$source]],
            ];
        }

        $action = 'attach-element';
        $payload = ['rootSource' => 'product', 'element' => $incoming, 'index' => 0];
        $layoutId = Uuid::randomHex();
        if ($persisted) {
            $this->repository()->create([[
                'id' => $layoutId,
                'name' => 'Attachment preserves authored mapping state',
                'version' => '1.0.0',
                'rootSource' => 'product',
                'layout' => [['id' => 'existing-container', 'component' => 'Sw:Grid:Container']],
            ]], Context::createDefaultContext());
            $action = $layoutId . '/attach-element';
            $payload = ['element' => $incoming, 'index' => 0];
        }

        $attached = $this->mutate($action, $payload)['layout'][0];

        static::assertNotSame($incoming['id'], $attached['id']);
        $copy = $nested ? $attached['slots']['content'][0] : $attached;
        static::assertNotSame($source['id'], $copy['id']);
        static::assertFalse($copy['properties']['showTaxInfo']);
        $expected = $source;
        unset($expected['id'], $copy['id']);
        static::assertEquals($expected, $copy);

        if ($persisted) {
            $stored = $this->repository()->search(new Criteria([$layoutId]), Context::createDefaultContext())->getEntities()->first();
            static::assertInstanceOf(ContentLayoutEntity::class, $stored);
            $storedElement = $stored->getLayout()[0];
            $storedCopy = $nested ? $storedElement->slots['content'][0] : $storedElement;
            static::assertFalse($storedCopy->property('showTaxInfo')?->asBool());
            $storedData = $storedCopy->jsonSerialize();
            unset($storedData['id']);
            static::assertEquals($expected, $storedData);
        }
    }

    /**
     * @return iterable<string, array{bool, bool, bool, bool}>
     */
    public static function attachmentStateProvider(): iterable
    {
        foreach (['draft' => false, 'persisted' => true] as $path => $persisted) {
            foreach (['root' => false, 'nested' => true] as $placement => $nested) {
                yield $path . ' ' . $placement . ' retains its existing mapping' => [$persisted, $nested, true, false];
                yield $path . ' ' . $placement . ' retains deliberate unmapping' => [$persisted, $nested, false, false];
                yield $path . ' ' . $placement . ' retains a selected product without remapping' => [$persisted, $nested, false, true];
            }
        }
    }

    public function testReattachingAnOrphanPreservesTheSelectedProduct(): void
    {
        $body = $this->mutate('insert-element', ['rootSource' => 'product', 'type' => 'Sw:Grid:Container']);
        $containerId = $body['layout'][0]['id'];
        $body = $this->mutate('insert-element', [
            'rootSource' => 'product',
            'layout' => $body['layout'],
            'type' => 'Sw:Product:PriceDisplay',
            'parentElementId' => $containerId,
            'slot' => 'content',
        ]);
        $body = $this->mutate('unmap-property', [
            'rootSource' => 'product',
            'layout' => $body['layout'],
            'elementId' => $body['layout'][0]['slots']['content'][0]['id'],
            'propertyKey' => 'product',
        ]);
        $selectedProductId = Uuid::randomHex();
        $body['layout'][0]['slots']['content'][0]['properties']['productId'] = $selectedProductId;
        $body = $this->mutate('replace-element', [
            'rootSource' => 'product',
            'layout' => $body['layout'],
            'elementId' => $containerId,
            'newType' => 'Sw:Product:Manufacturer',
        ]);
        static::assertCount(1, $body['orphaned']);
        $orphan = $body['orphaned'][0];
        static::assertArrayNotHasKey('acceptsContext', $orphan);

        $restored = $this->mutate('attach-element', [
            'rootSource' => 'product',
            'layout' => $body['layout'],
            'element' => $orphan,
        ])['layout'][1];

        static::assertNotSame($orphan['id'], $restored['id']);
        static::assertArrayNotHasKey('acceptsContext', $restored);
        static::assertSame($selectedProductId, $restored['properties']['productId']);
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
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function repository(): EntityRepository
    {
        return static::getContainer()->get('content_layout.repository');
    }
}
