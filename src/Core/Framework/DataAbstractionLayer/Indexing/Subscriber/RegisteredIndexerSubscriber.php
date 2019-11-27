<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IndexerMessageSender;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Store\Event\FirstRunWizardFinishedEvent;
use Shopware\Core\Framework\Update\Event\UpdatePreFinishEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RegisteredIndexerSubscriber implements EventSubscriberInterface
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

        $this->indexerMessageSender->partial(new \DateTimeImmutable(), $queuedIndexers);
        $this->indexerQueuer->finishIndexer($queuedIndexers);
    }
}
