<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlTemplate;

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

    public function __construct(
        private readonly SeoUrlUpdater $seoUrlUpdater,
        private readonly IteratorFactory $iteratorFactory,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly SeoUrlRouteRegistry $seoUrlRouteRegistry,
        private readonly MessageBusInterface $messageBus,
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
            $message->offset,
            self::ITERATE_BATCH_SIZE,
            $definition->isVersionAware() ? Defaults::LIVE_VERSION : null
        );

        $hexIds = array_values($iterator->fetch());
        if ($hexIds === []) {
            return;
        }

        $this->seoUrlUpdater->update($routeName, $hexIds);

        if (\count($hexIds) < self::ITERATE_BATCH_SIZE) {
            // Partial batch: the entity set is exhausted, no follow-up needed.
            return;
        }

        $this->messageBus->dispatch(
            new SeoUrlTemplateIndexingMessage($routeName, $entityName, $iterator->getOffset())
        );
    }
}
