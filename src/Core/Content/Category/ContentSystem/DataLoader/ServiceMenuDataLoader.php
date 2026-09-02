<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\ContentSystem\DataLoader;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\TreeItem;
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
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<CategoryCollection>
 */
#[Package('framework')]
class ServiceMenuDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'service_menu';

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
            new ConfigKeySpecification('rootId', ConfigKeyKind::Literal, 'string', required: false, hasDefault: true, default: 'service-navigation'),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $alias = $inputs->string('rootId');
        $rootId = $this->aliasResolver->resolve($alias, $context);

        // If the alias was not resolved (service category not configured), return empty collection
        if ($rootId === $alias && $alias === 'service-navigation') {
            return ContentDataLoaderResult::cachedExternally(new CategoryCollection());
        }

        // The alias resolution above must run on the raw, un-normalized configured value: NavigationAliasResolver
        // matches its alias constants case-sensitively, so normalizing first would turn an uppercase rendering
        // of a built-in alias into the recognized literal and change which branch runs. The early return just
        // above reads raw values too, but not for that reason: it also requires the raw alias to equal the
        // lowercase built-in name, so moving only this normalization earlier would not make an uppercase alias
        // take that branch.
        $rootId = u($rootId)->lower()->toString();

        // The early return above covers exactly one case: the built-in `service-navigation` alias on a sales
        // channel with no service category. Every other unresolved value arrives here intact, because
        // NavigationAliasResolver returns an unrecognized literal unchanged
        // (src/Core/Framework/ContentSystem/Adapter/FactoryHelper/NavigationAliasResolver.php:37) and hands
        // back `footer-navigation` itself when that optional category is unset (:36). Passing one on would
        // reach Uuid::fromHexToBytes() in NavigationRoute::getCategoryMetaInfo()
        // (src/Core/Content/Category/SalesChannel/NavigationRoute.php:163) and abort the whole render. The
        // guard runs after the lowercase because Uuid::VALID_PATTERN is lowercase-only while
        // Uuid::fromHexToBytes() accepts uppercase hex, so an uppercase configured id reaches the database and
        // guarding the raw value would reject an id that works.
        if (!Uuid::isValid($rootId)) {
            return ContentDataLoaderResult::notFound();
        }

        // A failure Shopware modelled as an HTTP outcome degrades the element; anything beneath that line,
        // such as a \TypeError, an \AssertionError, or a database driver failure, propagates. Catch the
        // covering ancestor rather than an enumerated set: the reachable set is open, and a decorator can
        // rewrap a named class into an unnamed one. NavigationLoader delegates to AbstractNavigationRoute,
        // whose TreeBuildingNavigationRoute decorator reaches NavigationRoute, and that throws
        // CategoryNotFoundException when the root category is missing from the tree.
        try {
            $tree = $this->navigationLoader->load($rootId, $context, $rootId, 1);
        } catch (ShopwareHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        $categories = new CategoryCollection(array_map(
            static fn (TreeItem $treeItem) => $treeItem->getCategory(),
            $tree->getTree()
        ));

        return ContentDataLoaderResult::cachedExternally($categories);
    }
}
