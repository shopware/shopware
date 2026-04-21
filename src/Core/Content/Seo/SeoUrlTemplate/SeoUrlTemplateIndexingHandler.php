<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlTemplate;

use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles {@see SeoUrlTemplateIndexingMessage} by iterating every entity of the
 * affected route in bounded batches and delegating regeneration to the
 * {@see SeoUrlUpdater}.
 *
 * @internal
 */
#[Package('inventory')]
#[AsMessageHandler]
final class SeoUrlTemplateIndexingHandler
{
    /**
     * Number of entity ids passed to the SEO URL updater in one call. Keeps memory
     * usage bounded for shops with many products or categories.
     */
    private const ITERATE_BATCH_SIZE = 250;

    public function __construct(
        private readonly SeoUrlUpdater $seoUrlUpdater,
        private readonly IteratorFactory $iteratorFactory,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly SeoUrlRouteRegistry $seoUrlRouteRegistry,
    ) {
    }

    public function __invoke(SeoUrlTemplateIndexingMessage $message): void
    {
        $routeName = $message->routeName;
        $entityName = $message->entityName;

        if ($routeName === '' || $entityName === '') {
            return;
        }

        if (!$this->definitionRegistry->has($entityName)) {
            return;
        }

        if ($this->seoUrlRouteRegistry->findByRouteName($routeName) === null) {
            return;
        }

        $definition = $this->definitionRegistry->getByEntityName($entityName);
        $iterator = $this->iteratorFactory->createIterator(
            $definition,
            null,
            self::ITERATE_BATCH_SIZE,
            $definition->isVersionAware() ? Defaults::LIVE_VERSION : null
        );

        while ($ids = $iterator->fetch()) {
            $hexIds = array_values($ids);

            if ($hexIds === []) {
                continue;
            }

            $this->seoUrlUpdater->update($routeName, $hexIds);
        }
    }
}
