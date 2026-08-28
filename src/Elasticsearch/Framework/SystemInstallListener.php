<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Shopware\Core\Framework\Event\SystemInstallCompletedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
#[AsEventListener]
class SystemInstallListener
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ElasticsearchIndexer $indexer,
        private readonly MessageBusInterface $messageBus
    ) {
    }

    public function __invoke(SystemInstallCompletedEvent $event): void
    {
        try {
            $this->index();
        } catch (\Throwable) {
            // Unreachable or misconfigured Elasticsearch must not fail system:install
        }
    }

    private function index(): void
    {
        $messagesToDispatch = [];
        $offset = null;
        while ($message = $this->indexer->iterate($offset)) {
            $offset = $message->getOffset();

            $messagesToDispatch[] = $message;
        }

        $lastMessage = end($messagesToDispatch);

        if (!$lastMessage instanceof ElasticsearchIndexingMessage) {
            return;
        }

        $lastMessage->markAsLastMessage();

        foreach ($messagesToDispatch as $message) {
            $this->messageBus->dispatch($message);
        }
    }
}
