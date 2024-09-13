<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\MessageQueueException;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;

#[Package('core')]
readonly class MessageQueueSizeRestrictListener
{
    /**
     * @see https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/quotas-messages.html#:~:text=The%20maximum%20is%20262%2C144%20bytes,message%20payload%20in%20Amazon%20S3.
     */
    private const MESSAGE_SIZE_LIMIT = 1024 * 256;

    /**
     * @internal
     */
    public function __construct(private SerializerInterface $serializer, private LoggerInterface $logger, private bool $enforceLimit)
    {
    }

    public function __invoke(SendMessageToTransportsEvent $event): void
    {
        /**
         * When the message is sent to the SyncTransport, it means that the message is not sent to any transport so it can be ignored.
         */
        foreach ($event->getSenders() as $sender) {
            if ($sender instanceof SyncTransport) {
                return;
            }
        }

        $encoded = $this->serializer->encode($event->getEnvelope());
        $messageLength = \strlen(json_encode($encoded, \JSON_THROW_ON_ERROR));

        if ($messageLength > self::MESSAGE_SIZE_LIMIT) {
            $messageName = $event->getEnvelope()->getMessage()::class;

            if ($this->enforceLimit) {
                throw MessageQueueException::queueMessageSizeExceeded($messageName);
            }

            $this->logger->critical('The message {message} exceeds 256KB size limit with {size}KB, in future such messages will be rejected', ['message' => $messageName, 'size' => $messageLength * 1024]);
        }
    }
}
