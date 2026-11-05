<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testReplacementReportsInvalidChildMappingsWithoutDroppingParentWiring(): void
    {
        $source = ['type' => 'root', 'id' => 'product', 'path' => 'missing'];
        $child = [
            'id' => 'child', 'component' => TestElementTypeLoader::MAPPABLE_PRIMITIVE,
            'properties' => ['text' => 'Child fallback'],
            'acceptsContext' => ['text' => ['type' => 'single', 'required' => false, 'scope' => 'root', 'source' => $source]],
        ];

        $body = $this->mutate('replace-element', [
            'layout' => [[
                'id' => 'parent', 'component' => 'Sw:Grid:Container',
                'slots' => ['content' => [$child]],
            ]],
            'elementId' => 'parent', 'newType' => 'Sw:Grid:Container', 'rootSource' => 'product',
        ]);

        static::assertSame([], $body['droppedWiring']);
        static::assertSame($source, $body['layout'][0]['slots']['content'][0]['acceptsContext']['text']['source']);
        static::assertSame('Child fallback', $body['layout'][0]['slots']['content'][0]['properties']['text']);
        $violations = array_values(array_filter(
            $body['diagnostics']['violations'],
            static fn (array $violation): bool => $violation['code'] === 'invalid_mapping',
        ));
        static::assertCount(1, $violations);
        static::assertSame('child', $violations[0]['elementId']);
        static::assertSame('text', $violations[0]['key']);
        static::assertTrue($body['diagnostics']['wellFormed']);
        static::assertFalse($body['diagnostics']['resolvable']);
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
            'presetId' => 'Sw:MediaAndText',
        ]);

        static::assertCount(2, $body['layout']);
        static::assertSame('block-a', $body['layout'][0]['id']);

        $container = $body['layout'][1];
        static::assertSame('Sw:Grid:Container', $container['component']);
        static::assertCount(2, $container['slots']['content']);
        static::assertSame('Sw:Media:Image', $container['slots']['content'][0]['component']);
        static::assertSame('Sw:Content:Text', $container['slots']['content'][1]['component']);

        // the inserted elements are fill-applied their type default binding, exactly like a manual insert, so the
        // image carries the core media wiring even though the preset spec authors no data requirements
        $image = $container['slots']['content'][0];
        static::assertSame(['media' => self::CORE_MEDIA_BINDING_ID], $image['attributedSpecifications']);
        static::assertArrayHasKey('media', $image['dataRequirements']);

        static::assertSame($container['id'], $body['affectedElementIds'][0]);
        static::assertNotContains('block-a', $body['affectedElementIds']);
        static::assertArrayHasKey('resolutions', $body);
    }

    #[TestDox('rejects an unknown preset id with a 404')]
    public function testInsertPresetRejectsUnknownPreset(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'insert-preset', [
            'layout' => [$this->element('block-a', TestElementTypeLoader::RESOLVABLE)],
            'presetId' => 'Sw:DoesNotExist',
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::LAYOUT_PRESET_NOT_FOUND, array_column($body['errors'], 'code'));
    }

    #[TestDox('inserts an element at the requested index of a container slot')]
    public function testInsertElementIntoContainerSlotAtIndex(): void
    {
        $component = TestElementTypeLoader::RESOLVABLE;

        $container = $this->element('container', $component);
        $container['slots'] = ['content' => [$this->element('block-b', $component)]];

        $body = $this->mutate('insert-element', [
            'layout' => [$container],
            'type' => $component,
            'parentElementId' => 'container',
            'slot' => 'content',
            'index' => 0,
        ]);

        static::assertCount(1, $body['layout']);
        static::assertSame([$body['affectedElementIds'][0], 'block-b'], array_column($body['layout'][0]['slots']['content'], 'id'));
    }

    #[TestDox('moves a root element to the requested index of a container slot')]
    public function testMoveElementIntoContainerSlotAtIndex(): void
    {
        $component = TestElementTypeLoader::RESOLVABLE;

        $container = $this->element('container', $component);
        $container['slots'] = ['content' => [$this->element('block-b', $component)]];

        $body = $this->mutate('move-element', [
            'layout' => [$container, $this->element('block-a', $component)],
            'elementId' => 'block-a',
            'newParentId' => 'container',
            'newSlot' => 'content',
            'index' => 0,
        ]);

        static::assertSame(['container'], array_column($body['layout'], 'id'));
        static::assertSame(['block-a', 'block-b'], array_column($body['layout'][0]['slots']['content'], 'id'));
    }

    #[TestDox('attaches a supplied subtree at the requested index of a container slot')]
    public function testAttachElementIntoContainerSlotAtIndex(): void
    {
        $component = TestElementTypeLoader::RESOLVABLE;

        $container = $this->element('container', $component);
        $container['slots'] = ['content' => [$this->element('block-b', $component)]];

        $body = $this->mutate('attach-element', [
            'layout' => [$container],
            'element' => $this->element('incoming', $component),
            'parentElementId' => 'container',
            'slot' => 'content',
            'index' => 0,
        ]);

        static::assertCount(1, $body['layout']);
        static::assertSame([$body['affectedElementIds'][0], 'block-b'], array_column($body['layout'][0]['slots']['content'], 'id'));
    }

    #[TestDox('inserts a core preset subtree into a container slot')]
    public function testInsertPresetIntoContainerSlot(): void
    {
        $body = $this->mutate('insert-preset', [
            'layout' => [$this->element('container', TestElementTypeLoader::RESOLVABLE)],
            'presetId' => 'Sw:MediaAndText',
            'parentElementId' => 'container',
            'slot' => 'content',
        ]);

        static::assertCount(1, $body['layout']);
        static::assertSame([$body['affectedElementIds'][0]], array_column($body['layout'][0]['slots']['content'], 'id'));
    }

    #[TestDox('rejects a structural impossibility with a 400')]
    public function testStructuralImpossibilityReturns400(): void
    {
        $component = TestElementTypeLoader::RESOLVABLE;

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'remove-element', [
            'layout' => [$this->element('block-a', $component)],
            'elementId' => 'ghost',
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::MUTATION_TARGET_NOT_FOUND, array_column($body['errors'], 'code'));
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

    public function testMalformedInlineTokenIsReportedWithoutAbortingTheDraftMutation(): void
    {
        $text = 'Hello {{map:product}}';
        $body = $this->mutate('insert-element', [
            'layout' => [[
                'id' => 'text-with-token',
                'component' => 'Sw:Content:Text',
                'properties' => ['text' => $text],
            ]],
            'type' => TestElementTypeLoader::RESOLVABLE,
            'rootSource' => 'product',
        ]);

        $violations = array_values(array_filter(
            $body['diagnostics']['violations'],
            static fn (array $violation): bool => $violation['code'] === 'invalid_mapping',
        ));
        static::assertCount(1, $violations);
        static::assertSame('text-with-token', $violations[0]['elementId']);
        static::assertSame('text', $violations[0]['key']);
        static::assertSame('binding', $violations[0]['scope']);
        static::assertSame($text, $body['layout'][0]['properties']['text']);
        static::assertCount(2, $body['layout']);
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

    #[TestDox('seeds the default product mapping on a freshly inserted element while retaining its resolvedBy loader')]
    public function testInsertElementMirrorsRootContextConsumerOntoCreatedElement(): void
    {
        // Sw:Product:PriceDisplay declares the product root source as its default mapping and keeps a
        // resolvedBy loader underneath it so unmapping restores the entity selection.
        $body = $this->mutate('insert-element', [
            'layout' => [],
            'type' => 'Sw:Product:PriceDisplay',
            'rootSource' => 'product',
        ]);

        $inserted = $body['layout'][0];
        static::assertSame(
            [
                'type' => 'single',
                'required' => false,
                'scope' => 'root',
                'source' => ['type' => 'root', 'id' => 'product'],
            ],
            $inserted['acceptsContext']['product'],
        );
        static::assertArrayHasKey('product', $inserted['dataRequirements'] ?? []);
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

    public function testMapPropertyReturnsTheMappedDraft(): void
    {
        $this->getBrowser(permissions: ['content_layout:read']);
        $source = ['type' => 'root', 'id' => 'category', 'path' => 'media'];

        $body = $this->mutate('map-property', [
            'elementId' => 'mapped-image',
            'propertyKey' => 'media',
            'source' => $source,
            'rootSource' => 'category',
            'layout' => [['id' => 'mapped-image', 'component' => 'Sw:Media:Image', 'properties' => ['height' => '250px', 'mediaId' => '0188fa1b2c3d4e5f6a7b8c9d0e1f2a40']]],
        ]);

        static::assertSame($source, $body['layout'][0]['acceptsContext']['media']['source']);
        static::assertSame('root', $body['layout'][0]['acceptsContext']['media']['scope']);
        static::assertSame('250px', $body['layout'][0]['properties']['height']);
        static::assertSame(['mapped-image'], $body['affectedElementIds']);
    }

    public function testMapPropertyRequiresReadPrivilege(): void
    {
        $browser = $this->getBrowser(permissions: []);

        $browser->jsonRequest('POST', self::BASE_URL . 'map-property', [
            'elementId' => 'image',
            'propertyKey' => 'media',
            'source' => ['type' => 'root', 'id' => 'category', 'path' => 'media'],
            'rootSource' => 'category',
        ]);

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
    }

    public function testMapPropertyRejectsAMalformedSourceWithTheMappingError(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'map-property', [
            'elementId' => 'mapped-image',
            'propertyKey' => 'media',
            'source' => ['type' => '', 'id' => 'category'],
            'rootSource' => 'category',
            'layout' => [['id' => 'mapped-image', 'component' => 'Sw:Media:Image']],
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::INVALID_MAPPING_SOURCE, array_column($body['errors'], 'code'));
    }

    /**
     * @param array<string, mixed> $rootSourcePayload
     */
    #[DataProvider('missingMappingRootSourceProvider')]
    public function testMovingElementsPreservesUnboundMappingsAndReportsInlineTokensRequiringASource(array $rootSourcePayload): void
    {
        $source = ['type' => 'root', 'id' => 'category', 'path' => 'media'];
        $body = $this->mutate('move-element', [
            ...$rootSourcePayload,
            'layout' => [
                [
                    'id' => 'mapped', 'component' => 'Sw:Media:Gallery', 'properties' => [],
                    'acceptsContext' => ['mediaItems' => ['type' => 'single', 'required' => false, 'scope' => 'root', 'source' => $source]],
                ],
                [
                    'id' => 'inline', 'component' => 'Sw:Content:Text',
                    'properties' => ['text' => 'Hello {{map:product.name}}'],
                ],
            ],
            'elementId' => 'inline', 'index' => 0,
        ]);

        static::assertSame(['inline', 'mapped'], array_column($body['layout'], 'id'));
        static::assertSame($source, $body['layout'][1]['acceptsContext']['mediaItems']['source']);
        $violations = array_values(array_filter(
            $body['diagnostics']['violations'],
            static fn (array $violation): bool => $violation['code'] === 'invalid_mapping',
        ));
        static::assertCount(1, $violations);
        static::assertSame('inline', $violations[0]['elementId']);
        static::assertSame('text', $violations[0]['key']);
        static::assertSame('binding', $violations[0]['scope']);
        static::assertSame(
            ContentSystemException::inlineMappingRequiresRootSource('product.name', 'text')->getMessage(),
            $violations[0]['message'],
        );
        static::assertTrue($body['diagnostics']['wellFormed']);
        static::assertFalse($body['diagnostics']['resolvable']);
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
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        // the ids 1 and 2 are absent from the layout, so a request that passed the boundary would be refused by the
        // op with mutationTargetNotFound: only the type violation names the string requirement
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        // one violation per id, joined into the single error entry's detail
        static::assertSame(
            ["This value should be of type string.\nThis value should be of type string."],
            array_column($body['errors'], 'detail'),
        );
        static::assertNotContains(ContentSystemException::MUTATION_TARGET_NOT_FOUND, array_column($body['errors'], 'code'));
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

    /**
     * @param array<string, mixed> $rootSourcePayload
     */
    #[DataProvider('missingMappingRootSourceProvider')]
    public function testMapPropertyRejectsAMissingRootSourceAtTheRequestBoundary(array $rootSourcePayload): void
    {
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'map-property', [
            'elementId' => 'missing-element',
            'propertyKey' => 'text',
            'source' => ['type' => 'root', 'id' => 'product', 'path' => 'name'],
            ...$rootSourcePayload,
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertNotContains(ContentSystemException::MAPPING_ROOT_SOURCE_MISSING, array_column($body['errors'], 'code'));
        static::assertNotContains(ContentSystemException::MUTATION_TARGET_NOT_FOUND, array_column($body['errors'], 'code'));
    }

    #[DataProvider('nonArrayMappingInputProvider')]
    public function testMappingMutationsRejectNonArrayInputsBeforeApplyingTheMutation(string $operation, string $field, mixed $value): void
    {
        $payload = [
            'elementId' => 'missing-element',
            'propertyKey' => 'text',
            'layout' => [],
            'rootSource' => 'product',
        ];

        if ($operation === 'map-property') {
            $payload['source'] = ['type' => 'root', 'id' => 'product', 'path' => 'name'];
        }

        $payload[$field] = $value;
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $operation, $payload);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertNotSame([], $body['errors']);
        static::assertNotContains(ContentSystemException::MUTATION_TARGET_NOT_FOUND, array_column($body['errors'], 'code'));
    }

    /**
     * @return iterable<string, array{string, string, mixed}>
     */
    public static function nonArrayMappingInputProvider(): iterable
    {
        yield 'map source rejects a string' => ['map-property', 'source', 'not-an-array'];
        yield 'map source rejects null' => ['map-property', 'source', null];
        yield 'map source rejects a number' => ['map-property', 'source', 42];
        yield 'map layout rejects a string' => ['map-property', 'layout', 'not-an-array'];
        yield 'map layout rejects null' => ['map-property', 'layout', null];
        yield 'map layout rejects a number' => ['map-property', 'layout', 42];
        yield 'unmap layout rejects a string' => ['unmap-property', 'layout', 'not-an-array'];
        yield 'unmap layout rejects null' => ['unmap-property', 'layout', null];
        yield 'unmap layout rejects a number' => ['unmap-property', 'layout', 42];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function missingMappingRootSourceProvider(): iterable
    {
        yield 'omitted source' => [[]];
        yield 'null source' => [['rootSource' => null]];
        yield 'empty source' => [['rootSource' => '']];
    }

    #[DataProvider('conflictingMappingConsumers')]
    public function testMapPropertyRejectsConflictingOrdinaryWiring(string $contextKey, ?string $propertyAlias): void
    {
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'map-property', [
            'layout' => [[
                'id' => 'price', 'component' => 'Sw:Product:PriceDisplay',
                'acceptsContext' => [$contextKey => [
                    'type' => 'single', 'required' => false, 'propertyAlias' => $propertyAlias,
                ]],
            ]],
            'elementId' => 'price', 'propertyKey' => 'product', 'rootSource' => 'product',
            'source' => ['type' => 'root', 'id' => 'product'],
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::PROPERTY_ALIAS_COLLISION, array_column($body['errors'], 'code'));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function conflictingMappingConsumers(): iterable
    {
        yield 'same destination' => ['product', null];
        yield 'alias writes the destination' => ['selectedProduct', 'product'];
        yield 'dotted consumer holds the same base' => ['product.manufacturer', null];
        yield 'same consumer key with a different destination' => ['product', 'otherProduct'];
    }

    public function testMapPropertyRejectsAnUnknownRootSourceBeforeApplyingTheMutation(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'map-property', [
            'elementId' => 'missing-element',
            'propertyKey' => 'text',
            'source' => ['type' => 'root', 'id' => 'product', 'path' => 'name'],
            'rootSource' => 'definitely-not-a-root-source',
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::UNKNOWN_ROOT_SOURCE, array_column($body['errors'], 'code'));
        static::assertNotContains(ContentSystemException::MUTATION_TARGET_NOT_FOUND, array_column($body['errors'], 'code'));
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

    // Doubles as the bind-element route-wiring check: a 400 with this app-level error code
    // (not a Symfony 404 route-not-found body) proves the request reached LayoutMutationController::bind().
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
        $resolutionsByKey = array_column($resolutions, null, 'key');
        static::assertArrayHasKey($key, $resolutionsByKey, \sprintf('No resolution found for key "%s"', $key));
        static::assertIsArray($resolutionsByKey[$key]);

        return $resolutionsByKey[$key];
    }
}
