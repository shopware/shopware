<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Middleware;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * @internal
 */
#[Package('framework')]
class RoutingOverwriteMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, string|list<string>> $routing
     */
    public function __construct(private readonly array $routing)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->last(ReceivedStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        if ($this->hasTransportStamp($envelope)) {
            return $stack->next()->handle($envelope, $stack);
        }

        $transports = $this->getTransports($envelope, $this->routing, true);

        if (empty($transports)) {
            return $stack->next()->handle($envelope, $stack);
        }

        return $stack
            ->next()
            ->handle(
                $envelope->with(new TransportNamesStamp($transports)),
                $stack
            );
    }

    private function hasTransportStamp(Envelope $envelope): bool
    {
        return $envelope->last(TransportNamesStamp::class) !== null;
    }

    /**
     * @param array<string, string|array<string>> $overwrites
     *
     * @return array<string>|string|null
     */
    private function getTransports(Envelope $message, array $overwrites, bool $inherited): array|string|null
    {
        $class = $message->getMessage()::class;

        if (\array_key_exists($class, $overwrites)) {
            return $overwrites[$class];
        }

        if (!$inherited) {
            return null;
        }

        foreach ($overwrites as $class => $transports) {
            if ($message instanceof $class) {
                return $transports;
            }
        }

        return null;
    }
}
