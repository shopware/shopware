<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Handler;

use League\OAuth2\Server\CryptKey;
use Shopware\Core\Framework\MessageQueue\Message\EncryptedMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class EncryptedMessageHandler implements MessageHandlerInterface
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

    public function __invoke(EncryptedMessage $message)
    {
        $originalMessage = $this->decryptMessage($message);

        $this->bus->dispatch(new Envelope($originalMessage, new ReceivedStamp()));
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
