<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * A data loader proving the binding convenience layer treats an extension-registered loader exactly like a shipped
 * one. Tagged `content_system.data_loader` in services_test.xml so the whole subsystem discovers it
 * through the production seam: the compiler pass dry-runs its `configSpecification()`/`@extends`, and the
 * canonicalizer and diagnostics see it through the real data-loader map.
 * It produces `MediaEntity` so its `entityName` key is FQCN-derivable and it wires onto the shipped `Sw:Media:Image`
 * type, and declares two required `propertyReference` keys plus a defaulted one, exercising multi-reference input
 * synthesis, the derived `required` flag, and per-key `UnfilledRequiredInput` gating, with zero changes to
 * `Binding/` or `Diagnostics/`.
 *
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<MediaEntity>
 */
#[Package('framework')]
class TestMultiReferenceGatingLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'test_multi_reference_gating';

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        // INVARIANT: this loader produces MediaEntity, so it must NEVER declare exactly one required
        // propertyReference key. Exactly one would make it tier-A-eligible for MediaEntity
        // (BindingSpecificationCanonicalizer::eligibleTierASources) and turn the shipped core:from-media-library
        // `media: mediaId` tier-A shorthand ambiguous, failing the binding registry build across the whole test
        // suite. Two required propertyReference keys keep it out of the tier-A set; the required keys also keep it
        // config-incomplete so it never auto-resolves a MediaEntity reference. Declared from literals only (the
        // compiler pass dry-runs this on a constructor-less instance).
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
            new ConfigKeySpecification('secondProperty', ConfigKeyKind::PropertyReference, 'string', required: true),
            new ConfigKeySpecification('activeProperty', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'activeId'),
        ]);
    }

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        // Never invoked by the proofs: diagnostics resolves the produced type, it does not load data.
        return ContentDataLoaderResult::notFound();
    }
}
