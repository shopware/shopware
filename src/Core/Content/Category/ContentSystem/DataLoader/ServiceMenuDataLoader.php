<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\ContentSystem\DataLoader;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
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
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

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
