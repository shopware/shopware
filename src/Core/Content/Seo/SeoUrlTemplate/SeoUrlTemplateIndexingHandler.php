<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlTemplate;

use Shopware\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handles {@see SeoUrlTemplateIndexingMessage} by regenerating one batch of
 * entities via the {@see SeoUrlUpdater} and dispatching a follow-up message
 * with the iterator offset for the next batch. Chaining bounded messages keeps
 * every handler invocation short, so worker time limits and message retries
 * never restart the whole catalog iteration from scratch.
 *
 * @internal
 */
#[Package('inventory')]
#[AsMessageHandler]
final class SeoUrlTemplateIndexingHandler
{
    /**
     * Number of entity ids processed per message. Keeps the runtime and memory of
     * a single message bounded for shops with many products or categories.
     */
    private const ITERATE_BATCH_SIZE = 250;

    /**
     * @param iterable<EntitySeoUrlRouteInterface> $entitySeoUrlRoutes
     */
    public function __construct(
        private readonly SeoUrlUpdater $seoUrlUpdater,
        private readonly IteratorFactory $iteratorFactory,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly SeoUrlRouteRegistry $seoUrlRouteRegistry,
        private readonly MessageBusInterface $messageBus,
        private readonly iterable $entitySeoUrlRoutes,
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

        if (!$this->isKnownRoute($routeName)) {
            return;
        }

        $definition = $this->definitionRegistry->getByEntityName($entityName);
        $iterator = $this->iteratorFactory->createIterator(
            $definition,
            $message->offset,
            self::ITERATE_BATCH_SIZE,
            $definition->isVersionAware() ? Defaults::LIVE_VERSION : null
        );

        $hexIds = array_values($iterator->fetch());
        if ($hexIds === []) {
            return;
        }

        // A partial batch means the entity set is exhausted, so no follow-up is needed.
        // Otherwise the follow-up is dispatched *before* the batch is processed: a batch
        // that keeps failing ends up in the failed transport, and dispatching afterwards
        // would let that single batch abort the rest of the chain, silently leaving the
        // remaining entities on the old template. Regeneration is idempotent, so the
        // duplicate follow-up of a retried batch only costs a repeated pass.
        if (\count($hexIds) === self::ITERATE_BATCH_SIZE) {
            $this->messageBus->dispatch(
                new SeoUrlTemplateIndexingMessage($routeName, $entityName, $iterator->getOffset())
            );
        }

        $this->seoUrlUpdater->update($routeName, $hexIds);
    }

    /**
     * Mirrors the route resolution of {@see SeoUrlUpdater::update()}: storefront routes
     * live in the {@see SeoUrlRouteRegistry}, while headless store-api routes are only
     * registered as `shopware.entity.seo_url.route` although their templates are equally
     * editable. Skipping the latter would silently drop the reindex for them.
     */
    private function isKnownRoute(string $routeName): bool
    {
        if ($this->seoUrlRouteRegistry->findByRouteName($routeName) !== null) {
            return true;
        }

        foreach ($this->entitySeoUrlRoutes as $entitySeoUrlRoute) {
            if ($entitySeoUrlRoute->getConfig()->getRouteName() === $routeName) {
                return true;
            }
        }

        return false;
    }
}
