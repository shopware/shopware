<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

class DefaultSenderLocator implements SendersLocatorInterface
{
    private SendersLocatorInterface $inner;

    private ?SenderInterface $defaultSender;

    private ?string $defaultSenderName;

    public function __construct(
        SendersLocatorInterface $inner,
        ?SenderInterface $defaultSender,
        ?string $defaultSenderName
    ) {
        $this->inner = $inner;
        $this->defaultSender = $defaultSender;
        $this->defaultSenderName = $defaultSenderName;
    }

    public function getSenders(Envelope $envelope): iterable
    {
        $foundSender = false;
        foreach ($this->inner->getSenders($envelope) as $senderAlias => $sender) {
            $foundSender = true;
            yield $senderAlias => $sender;
        }

        if (!$foundSender && $this->defaultSender !== null) {
            $senderAlias = $this->defaultSenderName ?? 0;
            yield $senderAlias => $this->defaultSender;
        }
    }
}
