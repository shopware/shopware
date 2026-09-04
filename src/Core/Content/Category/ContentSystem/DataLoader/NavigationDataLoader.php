<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\ContentSystem\DataLoader;

use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\NavigationAliasResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\String\u;

/**
 * Loads navigation tree data via NavigationLoaderInterface.
 *
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<Tree>
 */
#[Package('discovery')]
class NavigationDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'navigation';

    public function __construct(
        private readonly NavigationLoaderInterface $navigationLoader,
        private readonly NavigationAliasResolver $aliasResolver,
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('rootId', ConfigKeyKind::Literal, 'string', required: false, hasDefault: true, default: 'main-navigation'),
            new ConfigKeySpecification('depth', ConfigKeyKind::Literal, 'integer', required: false, hasDefault: false),
            new ConfigKeySpecification('activeProperty', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'activeId'),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $rootId = $this->aliasResolver->resolve($inputs->string('rootId'), $context);
        $rootId = u($rootId)->lower()->toString();

        // A recognized alias still resolves to itself when the sales channel has no such category
        // (service and footer navigation are both optional). Passing that on would reach
        // Uuid::fromHexToBytes() in NavigationRoute and abort the whole render. Guard after the lowercase
        // (Uuid::VALID_PATTERN is lowercase-only): NavigationLoaderConfigSerializer::decode() preserves the
        // configured case, and an uppercase configured id works against the database.
        if (!Uuid::isValid($rootId)) {
            return ContentDataLoaderResult::notFound();
        }

        // The referenced property carries the "{{categoryId}}" placeholder by default, which stays literal on a
        // layout not rooted on a category. Anything but an id falls back to the root rather than reaching
        // Uuid::fromHexToBytes() in NavigationRoute; same lowercase-before-guard order as above.
        $activeProperty = $inputs->stringOrNull('activeProperty');
        $activeId = $activeProperty === null ? null : u($activeProperty)->lower()->toString();

        if ($activeId === null || !Uuid::isValid($activeId)) {
            $activeId = $rootId;
        }

        // The one context-derived fallback: "depth" is declared without a default because the sales channel,
        // not the specification, supplies it.
        $depth = $inputs->intOrNull('depth') ?? $context->getSalesChannel()->getNavigationCategoryDepth();

        // Any ShopwareHttpException degrades the element to notFound(); everything else, such as a \TypeError
        // or a database driver failure, propagates. Why the catch is the covering ancestor and never an
        // enumerated union: src/Core/Framework/ContentSystem/Hydration/DataLoader/README.md#degradation-boundary
        // Known local throws: NavigationLoader delegates through TreeBuildingNavigationRoute to
        // NavigationRoute, which throws CategoryNotFoundException when the active or root category is
        // missing from the tree.
        try {
            $tree = $this->navigationLoader->load($activeId, $context, $rootId, $depth);
        } catch (ShopwareHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        return ContentDataLoaderResult::cachedExternally($tree);
    }
}
