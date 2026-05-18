<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Messenger\Middleware;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

#[Package('framework')]
class QueuedTimeMiddleware implements MiddlewareInterface
{
    // @TODO clock-bc: review public ctor change for BC
    public function __construct(
        private readonly ClockInterface $clock = new NativeClock(),
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // add a SentAtStamp if the envelope does not have one and is not in the receive phase
        if ($envelope->last(SentAtStamp::class) === null && $envelope->last(ReceivedStamp::class) === null) {
            $now = $this->clock->now();
            $envelope = $envelope->with(new SentAtStamp($now));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
