<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IndexerMessageSender;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Update\Event\UpdateFinishedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UpdateSubscriber implements EventSubscriberInterface
{
    /**
     * @var IndexerMessageSender
     */
    private $indexerMessageSender;

    /**
     * @var IndexerQueuer
     */
    private $indexerQueuer;

    public function __construct(IndexerQueuer $indexerQueuer, IndexerMessageSender $indexerMessageSender)
    {
        $this->indexerMessageSender = $indexerMessageSender;
        $this->indexerQueuer = $indexerQueuer;
    }

    public static function getSubscribedEvents()
    {
        return [
            UpdateFinishedEvent::class => 'updateFinished',
        ];
    }

    /**
     * @internal
     */
    public function updateFinished(UpdateFinishedEvent $event): void
    {
        $queuedIndexers = $this->indexerQueuer->getIndexers();

        if (empty($queuedIndexers)) {
            return;
        }

        $this->indexerMessageSender->partial(new \DateTimeImmutable(), $queuedIndexers);
        $this->indexerQueuer->finishIndexer($queuedIndexers);
    }
}
