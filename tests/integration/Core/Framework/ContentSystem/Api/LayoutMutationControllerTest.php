<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
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

    #[TestDox('mirrors a resolved root-ambient reference onto a freshly inserted element as a root-scope acceptsContext consumer')]
    public function testInsertElementMirrorsRootContextConsumerOntoCreatedElement(): void
    {
        // Sw:Product:PriceDisplay declares a bare SalesChannelProductEntity "product" reference: no resolvedBy
        // (so no default binding fills it) and no self-provided key, and the "product" root source offers that
        // exact FQCN, so every mirror skip clears and the live resolver, not a hand-built candidate, proves it.
        $body = $this->mutate('insert-element', [
            'layout' => [],
            'type' => 'Sw:Product:PriceDisplay',
            'rootSource' => 'product',
        ]);

        $inserted = $body['layout'][0];
        static::assertArrayNotHasKey('product', $inserted['dataRequirements'] ?? []);
        static::assertSame(
            ['type' => 'single', 'required' => false, 'scope' => 'root'],
            $inserted['acceptsContext']['product'],
        );
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

    #[TestDox('rejects an update-element-properties request that writes nothing and removes nothing with a 400')]
    public function testUpdatePropertiesRejectsAnEmptyRequest(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'update-element-properties', [
            'layout' => [$this->element('block-a', TestElementTypeLoader::DEFAULTED_PRIMITIVE)],
            'elementId' => 'block-a',
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('updateElementPropertiesEmpty', (string) $response->getContent());
    }

    #[TestDox('rejects a non-array values map on update-element-properties with a 400 at denormalization')]
    public function testUpdatePropertiesRejectsNonArrayValues(): void
    {
        // removeKeys names a primitive key the element type declares, so the request is non-empty and
        // the UpdateElementPropertiesNotEmpty constraint cannot supply the 400: only the non-array
        // values can.
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'update-element-properties', [
            'layout' => [$this->element('block-a', TestElementTypeLoader::DEFAULTED_PRIMITIVE)],
            'elementId' => 'block-a',
            'values' => 'not-a-map',
            'removeKeys' => ['headline'],
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
    }

    #[TestDox('rejects an unknown request field on update-element-properties with a 400 and the unknownRequestField code')]
    public function testUpdatePropertiesRejectsUnknownRequestField(): void
    {
        $component = TestElementTypeLoader::RESOLVABLE;

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'update-element-properties', [
            'layout' => [$this->element('block-a', $component)],
            'elementId' => 'block-a',
            'removeKeys' => ['headline'],
            'entityType' => 'product',
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::UNKNOWN_REQUEST_FIELD, array_column($body['errors'], 'code'));
        // the reported field name, not just the code: a rejection naming any other field must fail here
        static::assertContains(
            'The request contains unknown field(s): entityType. This endpoint rejects fields it does not declare.',
            array_column($body['errors'], 'detail'),
        );
    }

    #[TestDox('leaves a removed key carrying a type default absent in the draft response tree')]
    public function testUpdatePropertiesLeavesRemovedDefaultedKeyAbsent(): void
    {
        $element = $this->element('block-a', TestElementTypeLoader::DEFAULTED_PRIMITIVE);
        // carriedNote is undeclared on purpose: DEFAULTED_PRIMITIVE declares headline alone, so an undeclared
        // property is the only second key this element can carry past the route's declared-key gate.
        $element['properties'] = ['headline' => 'Authored headline', 'carriedNote' => 'Carried through untouched'];

        // the draft route runs no write boundary, so nothing reseeds the type default the removal dropped
        $body = $this->mutate('update-element-properties', [
            'layout' => [$element],
            'elementId' => 'block-a',
            'removeKeys' => ['headline'],
        ]);

        // the exact surviving map, not merely an empty one: a route that dropped every property would fail here
        static::assertSame(['carriedNote' => 'Carried through untouched'], $body['layout'][0]['properties']);
    }

    #[TestDox('writes a supplied primitive value onto the target element and returns it in the draft response tree')]
    public function testUpdatePropertiesWritesSuppliedPrimitiveValue(): void
    {
        $body = $this->mutate('update-element-properties', [
            'layout' => [$this->element('block-a', TestElementTypeLoader::DEFAULTED_PRIMITIVE)],
            'elementId' => 'block-a',
            'values' => ['headline' => 'Authored headline'],
        ]);

        static::assertSame(['headline' => 'Authored headline'], $body['layout'][0]['properties']);
        static::assertSame(['block-a'], $body['affectedElementIds']);
    }

    #[TestDox('rejects a language map carrying a non-language key with a 400 and the mutationPropertyLanguageKeyInvalid code')]
    public function testUpdatePropertiesRejectsANonLanguageMapKey(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'update-element-properties', [
            'layout' => [$this->element('block-a', TestElementTypeLoader::DEFAULTED_TRANSLATABLE)],
            'elementId' => 'block-a',
            'values' => ['tagline' => [Defaults::LANGUAGE_SYSTEM => 'Hallo', 'de-DE' => 'Hallo']],
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $errors = array_values(array_filter(
            $body['errors'],
            static fn (array $error): bool => $error['code'] === ContentSystemException::MUTATION_PROPERTY_LANGUAGE_KEY_INVALID,
        ));

        static::assertCount(1, $errors);
        // the reported map key on the error entry itself, not a whole-body substring: a rejection naming the
        // wrong key, or one merely echoing the payload back, must fail here
        static::assertSame('de-DE', $errors[0]['meta']['parameters']['languageKey'] ?? null);
    }

    #[TestDox('replaces a translatable property\'s language map on the target element, dropping a stored entry the supplied map omits, and returns it in the draft response tree')]
    public function testTranslateElementReplacesTheLanguageMap(): void
    {
        $element = $this->element('block-a', TestElementTypeLoader::DEFAULTED_TRANSLATABLE);
        $element['properties'] = ['tagline' => [Defaults::LANGUAGE_SYSTEM => 'Hello', $this->secondLanguageId() => 'Hallo']];

        // the supplied map omits the stored second-language entry, so a merge into the stored map would keep it
        $body = $this->mutate('translate-element', [
            'layout' => [$element],
            'elementId' => 'block-a',
            'values' => ['tagline' => [Defaults::LANGUAGE_SYSTEM => 'Hi']],
        ]);

        static::assertEquals(['tagline' => [Defaults::LANGUAGE_SYSTEM => 'Hi']], $body['layout'][0]['properties']);
        static::assertSame(['block-a'], $body['affectedElementIds']);
    }

    #[TestDox('rejects a translate-element value for a declared non-translatable property with a 400 and the mutationPropertyNotTranslatable code')]
    public function testTranslateElementRejectsANonTranslatableProperty(): void
    {
        // headline is declared but not translatable, so only the translate route's translatable-key gate rejects it,
        // with mutationPropertyNotTranslatable
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'translate-element', [
            'layout' => [$this->element('block-a', TestElementTypeLoader::DEFAULTED_PRIMITIVE)],
            'elementId' => 'block-a',
            'values' => ['headline' => [Defaults::LANGUAGE_SYSTEM => 'Hello']],
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $errors = array_values(array_filter(
            $body['errors'],
            static fn (array $error): bool => $error['code'] === ContentSystemException::MUTATION_PROPERTY_NOT_TRANSLATABLE,
        ));

        static::assertCount(1, $errors);
        static::assertSame('headline', $errors[0]['meta']['parameters']['key'] ?? null);
    }

    #[TestDox('rejects a translate-element request with an empty values map with a 400 carrying the values count violation')]
    public function testTranslateElementRejectsEmptyValues(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'translate-element', [
            'layout' => [$this->element('block-a', TestElementTypeLoader::DEFAULTED_TRANSLATABLE)],
            'elementId' => 'block-a',
            'values' => [],
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        // values carries the request's only Count constraint, so this message is the values violation
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(['This collection should contain 1 element or more.'], array_column($body['errors'], 'detail'));
    }

    #[TestDox('rejects an unknown request field on translate-element with a 400 and the unknownRequestField code')]
    public function testTranslateElementRejectsUnknownRequestField(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . 'translate-element', [
            'layout' => [$this->element('block-a', TestElementTypeLoader::DEFAULTED_TRANSLATABLE)],
            'elementId' => 'block-a',
            'values' => ['tagline' => [Defaults::LANGUAGE_SYSTEM => 'Hello']],
            'entityType' => 'product',
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::UNKNOWN_REQUEST_FIELD, array_column($body['errors'], 'code'));
        // the reported field name, not just the code: a rejection naming any other field must fail here
        static::assertContains(
            'The request contains unknown field(s): entityType. This endpoint rejects fields it does not declare.',
            array_column($body['errors'], 'detail'),
        );
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

    private function secondLanguageId(): string
    {
        $repository = $this->getContainer()->get('language.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        $criteria = (new Criteria())
            ->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('id', Defaults::LANGUAGE_SYSTEM)]))
            ->setLimit(1);

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertIsString($id, 'the base data carries a second language row beside the system language');

        return $id;
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
