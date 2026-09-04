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

        // Alias resolution above runs on the raw configured value: NavigationAliasResolver matches its alias
        // constants case-sensitively, so normalizing first would turn an uppercase rendering of a built-in
        // alias into the recognized literal and change which branch runs.
        $rootId = u($rootId)->lower()->toString();

        // The early return above covers exactly one case: the built-in `service-navigation` alias on a sales
        // channel with no service category. Every other unresolved value arrives here intact, because
        // NavigationAliasResolver returns an unrecognized literal unchanged and hands back `footer-navigation`
        // itself when that optional category is unset. Passing one on would reach Uuid::fromHexToBytes() in
        // NavigationRoute::getCategoryMetaInfo() and abort the whole render. Guard after the lowercase:
        // Uuid::VALID_PATTERN is lowercase-only, and an uppercase configured id works against the database.
        if (!Uuid::isValid($rootId)) {
            return ContentDataLoaderResult::notFound();
        }

        // Any ShopwareHttpException degrades the element to notFound(); everything else, such as a \TypeError
        // or a database driver failure, propagates. Why the catch is the covering ancestor and never an
        // enumerated union: src/Core/Framework/ContentSystem/Hydration/DataLoader/README.md#degradation-boundary
        // Known local throws: NavigationLoader delegates through TreeBuildingNavigationRoute to
        // NavigationRoute, which throws CategoryNotFoundException when the root category is missing from
        // the tree.
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
