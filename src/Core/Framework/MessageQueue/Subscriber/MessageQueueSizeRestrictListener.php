<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\MessageQueueException;
use Shopware\Core\Framework\MessageQueue\Service\MessageSizeCalculator;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;

#[Package('core')]
readonly class MessageQueueSizeRestrictListener
{
    /**
     * @see https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/quotas-messages.html
     * Maximum message size is 262144 (1024 * 256) bytes
     */
    private const MESSAGE_SIZE_LIMIT = 1024 * 256;

    /**
     * @internal
     */
    public function __construct(
        private MessageSizeCalculator $calculator,
        private LoggerInterface $logger,
        private bool $enforceLimit
    ) {
    }

    public function __invoke(SendMessageToTransportsEvent $event): void
    {
        /**
         * If the message is sent to the SyncTransport, it means that the message is not sent to any other transport so it can be ignored.
         */
        foreach ($event->getSenders() as $sender) {
            if ($sender instanceof SyncTransport) {
                return;
            }
        }

        $messageLengthInBytes = $this->calculator->size($event->getEnvelope());

        if ($messageLengthInBytes > self::MESSAGE_SIZE_LIMIT) {
            $messageName = $event->getEnvelope()->getMessage()::class;

            if ($this->enforceLimit) {
                throw MessageQueueException::queueMessageSizeExceeded($messageName);
            }

            $this->logger->critical(
                'The message "{message}" exceeds the 256 kB size limit with its size of {size} kB. With the next major version 6.7 such messages will be rejected.',
                [
                    'message' => $messageName,
                    'size' => $messageLengthInBytes / 1024,
                ]
            );
        }
    }
}
