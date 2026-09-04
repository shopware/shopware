<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class LayoutMutationControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const BASE_URL = '/api/_action/content-system/layout/';

    private const CORE_MEDIA_BINDING_ID = 'core:Sw:Media:Image';

    #[TestDox('inserts a registered element at the root and returns the re-resolved layout and diagnostics')]
    public function testInsertElement(): void
    {
        $component = TestElementTypeLoader::RESOLVABLE;

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

    #[TestDox('reports an unregistered style option in the 200 diagnostics body rather than rejecting the mutation')]
    public function testMutationReportsUnknownStyleOptionInDiagnostics(): void
    {
        $component = TestElementTypeLoader::RESOLVABLE;

        $element = $this->element('block-a', $component);
        $element['style'] = ['definitely-not-a-style-option' => ['xs' => 'x']];

        $body = $this->mutate('remove-element', [
            'layout' => [$element, $this->element('block-b', $component)],
            'elementId' => 'block-b',
        ]);

        static::assertFalse($body['diagnostics']['wellFormed']);

        $violations = array_values(array_filter(
            $body['diagnostics']['violations'],
            static fn (array $violation): bool => $violation['code'] === 'unknown_style_option',
        ));

        static::assertCount(1, $violations);
        static::assertSame('block-a', $violations[0]['elementId']);
        static::assertSame('definitely-not-a-style-option', $violations[0]['key']);
    }

    #[TestDox('removes an element and returns the trimmed layout')]
    public function testRemoveElement(): void
    {
        $component = TestElementTypeLoader::RESOLVABLE;

        $body = $this->mutate('remove-element', [
            'layout' => [$this->element('block-a', $component), $this->element('block-b', $component)],
            'elementId' => 'block-a',
        ]);

        static::assertSame(['block-b'], array_column($body['layout'], 'id'));
    }

    #[TestDox('reorders two root elements via a move to the root')]
    public function testMoveElement(): void
    {
        $component = TestElementTypeLoader::RESOLVABLE;

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
        [$from, $to] = [TestElementTypeLoader::RESOLVABLE, TestElementTypeLoader::UNRESOLVABLE];

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
        $component = TestElementTypeLoader::RESOLVABLE;

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
        $component = TestElementTypeLoader::RESOLVABLE;

        $body = $this->mutate('wrap-elements', [
            'layout' => [$this->element('block-a', $component), $this->element('block-b', $component)],
            'elementIds' => ['block-a', 'block-b'],
            'containerType' => $component,
            'slot' => 'content',
        ]);

        static::assertCount(1, $body['layout']);
        static::assertContains('block-a', $body['affectedElementIds']);
        static::assertContains('block-b', $body['affectedElementIds']);
        // the two roots are nested inside the new container's slot, not silently dropped or orphaned
        static::assertSame([], $body['orphaned']);
        static::assertSame(['block-a', 'block-b'], array_column($body['layout'][0]['slots']['content'], 'id'));
    }

    #[TestDox('unwraps a container and hoists its children to the root')]
    public function testUnwrapElement(): void
    {
        $component = TestElementTypeLoader::RESOLVABLE;

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
        $component = TestElementTypeLoader::RESOLVABLE;

        $body = $this->mutate('attach-element', [
            'layout' => [$this->element('block-a', $component)],
            'element' => $this->element('incoming', $component),
        ]);

        static::assertCount(2, $body['layout']);
        static::assertCount(1, $body['affectedElementIds']);
        static::assertNotSame('incoming', $body['affectedElementIds'][0]);
    }

    #[TestDox('inserts a core preset subtree at the root in a single mutation with server-minted ids')]
    public function testInsertPreset(): void
    {
        $body = $this->mutate('insert-preset', [
            'layout' => [$this->element('block-a', TestElementTypeLoader::RESOLVABLE)],
            'presetId' => 'core.media-and-text',
        ]);

        static::assertCount(2, $body['layout']);
        static::assertSame('block-a', $body['layout'][0]['id']);

        $container = $body['layout'][1];
        static::assertSame('Sw:Grid:Container', $container['component']);
        static::assertCount(2, $container['slots']['content']);
        static::assertSame('Sw:Media:Image', $container['slots']['content'][0]['component']);
        static::assertSame('Sw:Content:Text', $container['slots']['content'][1]['component']);

        static::assertSame($container['id'], $body['affectedElementIds'][0]);
        static::assertNotContains('block-a', $body['affectedElementIds']);
        static::assertArrayHasKey('resolutions', $body);
    }

    #[TestDox('rejects an unknown preset id with a 404')]
    public function testInsertPresetRejectsUnknownPreset(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'insert-preset', [
            'layout' => [$this->element('block-a', TestElementTypeLoader::RESOLVABLE)],
            'presetId' => 'core.does-not-exist',
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::LAYOUT_PRESET_NOT_FOUND, array_column($body['errors'], 'code'));
    }

    #[TestDox('rejects a structural impossibility with a 400 without persisting')]
    public function testStructuralImpossibilityReturns400(): void
    {
        $component = TestElementTypeLoader::RESOLVABLE;

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'remove-element', [
            'layout' => [$this->element('block-a', $component)],
            'elementId' => 'ghost',
        ]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode());
    }

    #[TestDox('rejects a numeric wiring key in the draft layout with a 400 invalidLayoutStructure before any mutation runs')]
    public function testNumericWiringKeyReturns400(): void
    {
        $component = TestElementTypeLoader::RESOLVABLE;

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'remove-element', [
            'layout' => [[
                'id' => 'block-a',
                'component' => $component,
                'properties' => [1 => 'x'],
            ]],
            'elementId' => 'block-a',
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::INVALID_LAYOUT_STRUCTURE, array_column($body['errors'], 'code'));
    }

    #[TestDox('rejects non-string wrap target ids at the request boundary with a 400')]
    public function testWrapRejectsNonStringElementIds(): void
    {
        $component = TestElementTypeLoader::RESOLVABLE;

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'wrap-elements', [
            'layout' => [$this->element('block-a', $component)],
            'elementIds' => [1, 2],
            'containerType' => $component,
            'slot' => 'content',
        ]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode());
    }

    #[TestDox('returns resolvability diagnostics in the body for an unresolvable root source rather than throwing')]
    public function testResolvabilityDiagnosticsReturnedNotThrown(): void
    {
        // insert an element that cannot resolve against the product root source (it requires an entity the source
        // does not provide); the route must report resolvable=false in a 200 body, never throw a 500
        $body = $this->mutate('insert-element', [
            'layout' => [$this->element('block-a', TestElementTypeLoader::RESOLVABLE)],
            'type' => TestElementTypeLoader::UNRESOLVABLE,
            'rootSource' => 'product',
        ]);

        static::assertFalse($body['diagnostics']['resolvable']);
    }

    #[TestDox('rejects an unknown rootSource with a 400 and the unknownRootSource code, never reaching resolve')]
    public function testRejectsUnknownRootSource(): void
    {
        $component = TestElementTypeLoader::RESOLVABLE;

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'insert-element', [
            'layout' => [$this->element('block-a', $component)],
            'type' => $component,
            'rootSource' => 'definitely-not-a-root-source',
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::UNKNOWN_ROOT_SOURCE, array_column($body['errors'], 'code'));
    }

    #[TestDox('rejects an unknown request field on a draft mutation with a 400 and the unknownRequestField code')]
    public function testRejectsUnknownRequestField(): void
    {
        $component = TestElementTypeLoader::RESOLVABLE;

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'insert-element', [
            'layout' => [$this->element('block-a', $component)],
            'type' => $component,
            'entityType' => 'product',
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::UNKNOWN_REQUEST_FIELD, array_column($body['errors'], 'code'));
    }

    // These two tests cover the negative paths that need no shipped specification, and double as the
    // bind-element route-wiring check: a 400 with this app-level error code (not a Symfony 404
    // route-not-found body) proves the request reached LayoutMutationController::bind(). The positive
    // round trip against the real shipped core:Sw:Media:Image default follows below.
    #[TestDox('rejects an unknown bindingSpecificationId with a 400 and the bindingSpecificationNotFound code')]
    public function testBindElementRejectsUnknownBindingSpecification(): void
    {
        $component = TestElementTypeLoader::RESOLVABLE;

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'bind-element', [
            'layout' => [$this->element('block-a', $component)],
            'elementId' => 'block-a',
            'bindingSpecificationId' => 'ghost:not-a-spec',
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::BINDING_SPECIFICATION_NOT_FOUND, array_column($body['errors'], 'code'));
    }

    #[TestDox('inlines the core Sw:Media:Image default specification\'s wiring and attribution on a draft bind')]
    public function testBindElementInlinesCoreSpecificationWiringAndAttribution(): void
    {
        $body = $this->mutate('bind-element', [
            'layout' => [$this->element('img-1', 'Sw:Media:Image')],
            'elementId' => 'img-1',
            'bindingSpecificationId' => self::CORE_MEDIA_BINDING_ID,
        ]);

        $bound = $body['layout'][0];
        static::assertSame('img-1', $bound['id']);
        static::assertSame(
            ['key' => 'media', 'source' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            $bound['dataRequirements']['media']
        );
        static::assertSame(['media' => self::CORE_MEDIA_BINDING_ID], $bound['attributedSpecifications']);
    }

    #[TestDox('resolves the bound media reference via CandidateOrigin::Stored once mediaId is filled in on the bound draft')]
    public function testBoundImageWithMediaIdFilledResolvesMediaViaStoredWiring(): void
    {
        $bound = $this->mutate('bind-element', [
            'layout' => [$this->element('img-1', 'Sw:Media:Image')],
            'elementId' => 'img-1',
            'bindingSpecificationId' => self::CORE_MEDIA_BINDING_ID,
        ])['layout'][0];

        $bound['properties']['mediaId'] = 'a-media-id';

        $this->getBrowser()->jsonRequest('POST', '/api/_action/content-system/layout/diagnose', ['layout' => [$bound]]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $diagnosis = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $mediaResolution = $this->resolutionFor($diagnosis['resolutions']['img-1'], 'media');

        static::assertNotNull($mediaResolution['resolved']);
        static::assertSame('stored', $mediaResolution['resolved']['origin']);
    }

    #[TestDox('applies the core Sw:Media:Image default specification atomically when inserting a fresh image on the draft route')]
    public function testInsertElementAppliesCoreBindingWiringAndAttribution(): void
    {
        $body = $this->mutate('insert-element', [
            'layout' => [],
            'type' => 'Sw:Media:Image',
            'bindingSpecificationId' => self::CORE_MEDIA_BINDING_ID,
        ]);

        $inserted = $body['layout'][0];
        static::assertSame(
            ['key' => 'media', 'source' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            $inserted['dataRequirements']['media']
        );
        static::assertSame(['media' => self::CORE_MEDIA_BINDING_ID], $inserted['attributedSpecifications']);
    }

    #[TestDox('auto-applies the core Sw:Media:Image default specification on a fresh image insert carrying no bindingSpecificationId')]
    public function testInsertElementAutoAppliesCoreDefaultWithoutBindingSpecificationId(): void
    {
        // No bindingSpecificationId is sent, so the media wiring and attribution can only come from the type's
        // auto-applied default (the byType()/isDefault() fill path), not from the overwriting apply() the explicit
        // bindingSpecificationId tests drive: a wrong-result regression in that fill path leaves media unwired here.
        $body = $this->mutate('insert-element', [
            'layout' => [],
            'type' => 'Sw:Media:Image',
        ]);

        $inserted = $body['layout'][0];
        static::assertSame(
            ['key' => 'media', 'source' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            $inserted['dataRequirements']['media']
        );
        static::assertSame(['media' => self::CORE_MEDIA_BINDING_ID], $inserted['attributedSpecifications']);
    }

    #[TestDox('rejects an insert whose bindingSpecificationId type does not match the inserted type with a 400 bindingTypeMismatch')]
    public function testInsertElementRejectsMismatchedBindingType(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'insert-element', [
            'layout' => [],
            'type' => 'Sw:Content:Text',
            'bindingSpecificationId' => self::CORE_MEDIA_BINDING_ID,
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::BINDING_TYPE_MISMATCH, array_column($body['errors'], 'code'));
    }

    #[TestDox('treats an empty rootSource as absent and evaluates only well-formedness without gating')]
    public function testTreatsEmptyRootSourceAsAbsent(): void
    {
        $component = TestElementTypeLoader::RESOLVABLE;

        $body = $this->mutate('insert-element', [
            'layout' => [$this->element('block-a', $component)],
            'type' => $component,
            'rootSource' => '',
        ]);

        static::assertTrue($body['diagnostics']['wellFormed']);
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

    /**
     * @param list<array<string, mixed>> $resolutions
     *
     * @return array<string, mixed>
     */
    private function resolutionFor(array $resolutions, string $key): array
    {
        foreach ($resolutions as $resolution) {
            if ($resolution['key'] === $key) {
                return $resolution;
            }
        }

        static::fail(\sprintf('No resolution found for key "%s"', $key));
    }
}
