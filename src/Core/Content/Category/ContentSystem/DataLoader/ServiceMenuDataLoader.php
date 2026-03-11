<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\ContentSystem\DataLoader;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\NavigationAliasResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
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

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $config = $requirement->config;

        if (!$config instanceof ServiceMenuLoaderConfig) {
            return ContentDataLoaderResult::notFound();
        }

        $alias = $config->rootId ?? 'service-navigation';
        $rootId = $this->aliasResolver->resolve($alias, $context);

        // If the alias was not resolved (service category not configured), return empty collection
        if ($rootId === $alias && $alias === 'service-navigation') {
            return ContentDataLoaderResult::cachedExternally(new CategoryCollection());
        }

        try {
            $tree = $this->navigationLoader->load($rootId, $context, $rootId, 1);
        } catch (CategoryNotFoundException) {
            return ContentDataLoaderResult::notFound();
        }

        $categories = new CategoryCollection(array_map(
            static fn (TreeItem $treeItem) => $treeItem->getCategory(),
            $tree->getTree()
        ));

        return ContentDataLoaderResult::cachedExternally($categories);
    }
}
