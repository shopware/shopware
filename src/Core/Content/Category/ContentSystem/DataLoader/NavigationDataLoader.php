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
        // Uuid::fromHexToBytes() in NavigationRoute and abort the whole render. The guard runs after the
        // lowercase because Uuid::VALID_PATTERN is lowercase-only while Uuid::fromHexToBytes() accepts
        // uppercase hex, so an uppercase configured id reaches the database and guarding the raw value would
        // reject an id that works. NavigationLoaderConfigSerializer::decode() preserves the configured case.
        if (!Uuid::isValid($rootId)) {
            return ContentDataLoaderResult::notFound();
        }

        // The referenced property carries the "{{categoryId}}" placeholder by default, which stays literal on a
        // layout not rooted on a category. Anything but an id therefore falls back rather than reaching
        // Uuid::fromHexToBytes() in NavigationRoute. LoaderInputResolver::dereference() hands back the stored
        // string unchanged, so the same lowercase-before-guard order applies here.
        $activeProperty = $inputs->stringOrNull('activeProperty');
        $activeId = $activeProperty === null ? null : u($activeProperty)->lower()->toString();

        if ($activeId === null || !Uuid::isValid($activeId)) {
            $activeId = $rootId;
        }

        // The one context-derived fallback: "depth" is declared without a default because the sales channel,
        // not the specification, supplies it.
        $depth = $inputs->intOrNull('depth') ?? $context->getSalesChannel()->getNavigationCategoryDepth();

        // A failure Shopware modelled as an HTTP outcome degrades the element; anything beneath that line,
        // such as a \TypeError, an \AssertionError, or a database driver failure, propagates. Catch the
        // covering ancestor rather than an enumerated set: the reachable set is open, and a decorator can
        // rewrap a named class into an unnamed one. NavigationLoader delegates to AbstractNavigationRoute,
        // whose TreeBuildingNavigationRoute decorator reaches NavigationRoute, and that throws
        // CategoryNotFoundException when the active or root category is missing from the tree.
        try {
            $tree = $this->navigationLoader->load($activeId, $context, $rootId, $depth);
        } catch (ShopwareHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        return ContentDataLoaderResult::cachedExternally($tree);
    }
}
