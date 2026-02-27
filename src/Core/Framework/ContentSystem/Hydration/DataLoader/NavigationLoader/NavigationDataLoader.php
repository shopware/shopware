<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\NavigationLoader;

use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\NavigationAliasResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads navigation tree data via NavigationLoaderInterface.
 *
 * Config:
 * - rootId: Navigation root ID or alias (main-navigation, service-navigation, footer-navigation)
 * - depth: Navigation tree depth (default: 2)
 * - activeProperty: Element property name to read active category ID from (default: activeId)
 *
 * @internal
 *
 * @final
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

    public function getDecorated(): AbstractContentDataLoader
    {
        throw new DecorationPatternException(self::class);
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

        if (!$config instanceof NavigationLoaderConfig) {
            return ContentDataLoaderResult::notFound();
        }

        // Resolve root ID from config or use sales channel's navigation category
        $rootId = $config->rootId ?? 'main-navigation';
        $rootId = $this->aliasResolver->resolve($rootId, $context);

        // Get active ID from element property or use root as active
        $activeProperty = $config->activeProperty;
        $activeId = $element->getProperty($activeProperty);

        if (!\is_string($activeId) || $activeId === '') {
            $activeId = $rootId;
        }

        $tree = $this->navigationLoader->load($activeId, $context, $rootId, $config->depth);

        // NavigationLoader handles its own caching internally
        return ContentDataLoaderResult::cachedExternally($tree);
    }
}
