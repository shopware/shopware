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
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads navigation tree data via NavigationLoaderInterface.
 *
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<Tree>
 */
#[Package('framework')]
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
            new ConfigKeySpecification('depth', ConfigKeyKind::Literal, 'integer', required: false, hasDefault: true, default: NavigationLoaderConfig::DEFAULT_DEPTH),
            new ConfigKeySpecification('activeProperty', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'activeId'),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        // Resolve root ID from config or use sales channel's navigation category
        $rootId = $this->aliasResolver->resolve($inputs->string('rootId'), $context);

        // Get active ID from the referenced element property or use root as active
        $activeId = $inputs->stringOrNull('activeProperty');

        if ($activeId === null || $activeId === '') {
            $activeId = $rootId;
        }

        $tree = $this->navigationLoader->load($activeId, $context, $rootId, $inputs->int('depth'));

        // NavigationLoader handles its own caching internally
        return ContentDataLoaderResult::cachedExternally($tree);
    }
}
