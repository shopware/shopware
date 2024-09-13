<?php

namespace Shopware\Core\Framework\Adapter\Messenger\Middleware;


use Shopware\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\SentStamp;

class QueuedTimeMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->last(SentAtStamp::class) === null && $envelope->last(SentStamp::class) === null) {
            $envelope = $envelope->with(new SentAtStamp(time()));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
