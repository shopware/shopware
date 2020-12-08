<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Handler;

use League\OAuth2\Server\CryptKey;
use Shopware\Core\Framework\MessageQueue\Message\EncryptedMessage;
use Shopware\Core\Framework\MessageQueue\Stamp\DecryptedStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class EncryptedMessageHandler extends AbstractMessageHandler
{
    /**
     * @var CryptKey
     */
    private $privateKey;

    /**
     * @var MessageBusInterface
     */
    private $bus;

    public function __construct(MessageBusInterface $bus, $privateKey)
    {
        $this->bus = $bus;
        if (!$privateKey instanceof CryptKey) {
            $privateKey = new CryptKey($privateKey);
        }
        $this->privateKey = $privateKey;
    }

    /**
     * @param EncryptedMessage $message
     */
    public function handle($message): void
    {
        $originalMessage = $this->decryptMessage($message);

        $this->bus->dispatch(new Envelope(
            $originalMessage,
            [
                new ReceivedStamp('null'),
                new DecryptedStamp(),
            ]
        ));
    }

    public static function getHandledMessages(): iterable
    {
        return [EncryptedMessage::class];
    }

    private function decryptMessage(EncryptedMessage $message): object
    {
        $key = openssl_pkey_get_private($this->privateKey->getKeyPath(), $this->privateKey->getPassPhrase());
        openssl_private_decrypt(
            $message->getMessage(),
            $decryptedMessage,
            $key
        );

        return unserialize($decryptedMessage);
    }
}
