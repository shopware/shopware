<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use League\OAuth2\Server\CryptKey;
use Shopware\Core\Framework\MessageQueue\Message\EncryptedMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class EncryptedBus implements MessageBusInterface
{
    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    /**
     * @var CryptKey
     */
    private $publicKey;

    public function __construct(
        MessageBusInterface $messageBus,
        $publicKey
    ) {
        $this->messageBus = $messageBus;
        if (!$publicKey instanceof CryptKey) {
            $publicKey = new CryptKey($publicKey);
        }
        $this->publicKey = $publicKey;
    }

    /**
     * @param object|Envelope $message
     */
    public function dispatch($message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);

        $envelope = $this->encryptMessage($envelope);

        return $this->messageBus->dispatch($envelope);
    }

    private function encryptMessage(Envelope $envelope): Envelope
    {
        $serializedMessage = serialize($envelope->getMessage());
        $key = openssl_pkey_get_public($this->publicKey->getKeyPath());
        openssl_public_encrypt(
            $serializedMessage,
            $encryptedMessage,
            $key
        );

        $allStamps = $envelope->all() ? array_merge(...array_values($envelope->all())) : [];

        return new Envelope(new EncryptedMessage($encryptedMessage), $allStamps);
    }
}
