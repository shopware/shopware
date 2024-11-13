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
     * @param string[] $skipEnforceSizeMessages
     *
     * @internal
     */
    public function __construct(
        private readonly MessageSizeCalculator $calculator,
        private readonly LoggerInterface $logger,
        private readonly bool $enforceLimit,
        private readonly array $skipEnforceSizeMessages
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

        $message = $event->getEnvelope()->getMessage();
        foreach ($this->skipEnforceSizeMessages as $skipMessage) {
            if ($message instanceof $skipMessage) {
                return;
            }
        }

        $messageLengthInBytes = $this->calculator->size($event->getEnvelope());

        if ($messageLengthInBytes > self::MESSAGE_SIZE_LIMIT) {
            if ($this->enforceLimit) {
                throw MessageQueueException::queueMessageSizeExceeded($message::class);
            }

            $this->logger->critical(
                'The message "{message}" exceeds the 256 kB size limit with its size of {size} kB. With the next major version 6.7 such messages will be rejected.',
                [
                    'message' => $message::class,
                    'size' => $messageLengthInBytes / 1024,
                ]
            );
        }
    }
}
