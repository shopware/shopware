<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Messenger\Middleware;

use Shopware\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

#[Package('framework')]
class QueuedTimeMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // add a SentAtStamp if the envelope does not have one and is not in the receive phase
        if ($envelope->last(SentAtStamp::class) === null && $envelope->last(ReceivedStamp::class) === null) {
            $now = new \DateTimeImmutable('@' . time());
            $envelope = $envelope->with(new SentAtStamp($now));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
