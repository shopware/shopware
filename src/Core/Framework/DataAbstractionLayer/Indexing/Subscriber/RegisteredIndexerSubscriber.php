<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Store\Event\FirstRunWizardFinishedEvent;
use Shopware\Core\Framework\Update\Event\UpdatePreFinishEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RegisteredIndexerSubscriber implements EventSubscriberInterface
{
    /**
     * @var IndexerQueuer
     */
    private $indexerQueuer;

    /**
     * @var EntityIndexerRegistry
     */
    private $indexerRegistry;

    public function __construct(IndexerQueuer $indexerQueuer, EntityIndexerRegistry $indexerRegistry)
    {
        $this->indexerQueuer = $indexerQueuer;
        $this->indexerRegistry = $indexerRegistry;
    }

    public static function getSubscribedEvents()
    {
        return [
            UpdatePreFinishEvent::class => 'runRegisteredIndexers',
            FirstRunWizardFinishedEvent::class => 'runRegisteredIndexers',
        ];
    }

    /**
     * @internal
     */
    public function runRegisteredIndexers(): void
    {
        $queuedIndexers = $this->indexerQueuer->getIndexers();

        if (empty($queuedIndexers)) {
            return;
        }

        $this->indexerQueuer->finishIndexer($queuedIndexers);

        $indexer = array_filter($queuedIndexers, function ($indexer) {
            return $this->indexerRegistry->has($indexer);
        });

        if (!empty($indexer)) {
            $this->indexerRegistry->sendIndexingMessage($indexer);
        }
    }
}
