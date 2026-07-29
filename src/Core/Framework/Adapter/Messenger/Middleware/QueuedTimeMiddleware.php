<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Messenger\Middleware;

use Shopware\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

#[Package('framework')]
class QueuedTimeMiddleware implements MiddlewareInterface
{
    use ClockAwareTrait;

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // add a SentAtStamp if the envelope does not have one and is not in the receive phase
        if ($envelope->last(SentAtStamp::class) === null && $envelope->last(ReceivedStamp::class) === null) {
            $envelope = $envelope->with(new SentAtStamp($this->now()));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
