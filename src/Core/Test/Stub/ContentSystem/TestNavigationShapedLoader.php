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
 * A navigation-shaped data loader: like the shipped `navigation` loader, its only `propertyReference`
 * key is defaulted, so a required reference wired through it resolves without ever demanding a stored input value;
 * it never raises `UnfilledRequiredInput`. Tagged `content_system.data_loader` in services_test.xml. It produces
 * `MediaEntity` so it can wire onto the shipped `Sw:Media:Image` type's required `media` reference.
 *
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<MediaEntity>
 */
#[Package('framework')]
class TestNavigationShapedLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'test_navigation_shaped';

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        // INVARIANT: this loader produces MediaEntity, so it must NEVER declare a required propertyReference key.
        // Exactly one would make it tier-A-eligible for MediaEntity and break the shipped core:from-media-library
        // `media: mediaId` shorthand at registry build. The required `entity` key is deliberate: it keeps the loader
        // config-incomplete so it never becomes an auto-resolving candidate for a MediaEntity reference. The single
        // defaulted propertyReference key is what makes this the navigation shape (never gates). Declared from
        // literals only (the compiler pass dry-runs this on a constructor-less instance).
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
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
