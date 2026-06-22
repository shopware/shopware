<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class LayoutMutationControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const BASE_URL = '/api/_action/content-system/layout/';

    #[TestDox('inserts a registered element at the root and returns the re-resolved layout and diagnostics')]
    public function testInsertElement(): void
    {
        $component = $this->registeredComponent();

        $body = $this->mutate('insert-element', [
            'layout' => [$this->element('block-a', $component)],
            'type' => $component,
        ]);

        static::assertCount(2, $body['layout']);
        static::assertCount(1, $body['affectedElementIds']);
        static::assertNotSame('block-a', $body['affectedElementIds'][0]);
        static::assertTrue($body['diagnostics']['wellFormed']);
        static::assertArrayHasKey('resolutions', $body);
        static::assertSame([], $body['orphaned']);
    }

    #[TestDox('removes an element and returns the trimmed layout')]
    public function testRemoveElement(): void
    {
        $component = $this->registeredComponent();

        $body = $this->mutate('remove-element', [
            'layout' => [$this->element('block-a', $component), $this->element('block-b', $component)],
            'elementId' => 'block-a',
        ]);

        static::assertSame(['block-b'], array_column($body['layout'], 'id'));
    }

    #[TestDox('reorders two root elements via a move to the root')]
    public function testMoveElement(): void
    {
        $component = $this->registeredComponent();

        $body = $this->mutate('move-element', [
            'layout' => [$this->element('block-a', $component), $this->element('block-b', $component)],
            'elementId' => 'block-b',
            'index' => 0,
        ]);

        static::assertSame(['block-b', 'block-a'], array_column($body['layout'], 'id'));
    }

    #[TestDox('replaces an element keeping its id and swapping the component to the new type')]
    public function testReplaceElement(): void
    {
        [$from, $to] = $this->twoRegisteredComponents();

        $body = $this->mutate('replace-element', [
            'layout' => [$this->element('block-a', $from)],
            'elementId' => 'block-a',
            'newType' => $to,
        ]);

        static::assertSame('block-a', $body['layout'][0]['id']);
        static::assertSame($to, $body['layout'][0]['component']);
        static::assertSame(['block-a'], $body['affectedElementIds']);
    }

    #[TestDox('duplicates an element with a server-minted id')]
    public function testDuplicateElement(): void
    {
        $component = $this->registeredComponent();

        $body = $this->mutate('duplicate-element', [
            'layout' => [$this->element('block-a', $component)],
            'elementId' => 'block-a',
        ]);

        static::assertCount(2, $body['layout']);
        static::assertCount(1, $body['affectedElementIds']);
        static::assertNotSame('block-a', $body['affectedElementIds'][0]);
    }

    #[TestDox('wraps two sibling roots into a freshly minted container')]
    public function testWrapElements(): void
    {
        $component = $this->registeredComponent();

        $body = $this->mutate('wrap-elements', [
            'layout' => [$this->element('block-a', $component), $this->element('block-b', $component)],
            'elementIds' => ['block-a', 'block-b'],
            'containerType' => $component,
            'slot' => 'content',
        ]);

        static::assertCount(1, $body['layout']);
        static::assertContains('block-a', $body['affectedElementIds']);
        static::assertContains('block-b', $body['affectedElementIds']);
    }

    #[TestDox('unwraps a container and hoists its children to the root')]
    public function testUnwrapElement(): void
    {
        $component = $this->registeredComponent();

        $container = $this->element('container', $component);
        $container['slots'] = ['content' => [$this->element('block-a', $component), $this->element('block-b', $component)]];

        $body = $this->mutate('unwrap-element', [
            'layout' => [$container],
            'containerElementId' => 'container',
        ]);

        static::assertSame(['block-a', 'block-b'], array_column($body['layout'], 'id'));
    }

    #[TestDox('attaches a supplied subtree to the draft with a server-minted id')]
    public function testAttachElement(): void
    {
        $component = $this->registeredComponent();

        $body = $this->mutate('attach-element', [
            'layout' => [$this->element('block-a', $component)],
            'element' => $this->element('incoming', $component),
        ]);

        static::assertCount(2, $body['layout']);
        static::assertCount(1, $body['affectedElementIds']);
        static::assertNotSame('incoming', $body['affectedElementIds'][0]);
    }

    #[TestDox('rejects a structural impossibility with a 400 without persisting')]
    public function testStructuralImpossibilityReturns400(): void
    {
        $component = $this->registeredComponent();

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'remove-element', [
            'layout' => [$this->element('block-a', $component)],
            'elementId' => 'ghost',
        ]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode());
    }

    #[TestDox('rejects non-string wrap target ids at the request boundary with a 400')]
    public function testWrapRejectsNonStringElementIds(): void
    {
        $component = $this->registeredComponent();

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'wrap-elements', [
            'layout' => [$this->element('block-a', $component)],
            'elementIds' => [1, 2],
            'containerType' => $component,
            'slot' => 'content',
        ]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode());
    }

    #[TestDox('returns binding diagnostics in the body for a bound source rather than throwing')]
    public function testBindingDiagnosticsReturnedNotThrown(): void
    {
        $component = $this->registeredComponent();

        $body = $this->mutate('insert-element', [
            'layout' => [$this->element('block-a', $component)],
            'type' => $component,
            'entityType' => 'product',
        ]);

        static::assertArrayHasKey('resolvable', $body['diagnostics']);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function mutate(string $action, array $payload): array
    {
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $action, $payload);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function element(string $id, string $component): array
    {
        return ['id' => $id, 'component' => $component, 'properties' => []];
    }

    private function registeredComponent(): string
    {
        $types = $this->getContainer()->get(ContentSystemElementTypeRegistry::class)->all();
        $name = array_key_first($types);
        static::assertIsString($name);

        return $name;
    }

    /**
     * @return array{string, string} two distinct registered component names
     */
    private function twoRegisteredComponents(): array
    {
        $names = array_keys($this->getContainer()->get(ContentSystemElementTypeRegistry::class)->all());
        static::assertGreaterThanOrEqual(2, \count($names));

        return [$names[0], $names[1]];
    }
}
