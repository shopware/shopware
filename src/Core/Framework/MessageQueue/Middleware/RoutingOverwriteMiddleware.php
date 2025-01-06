<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Middleware;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * @internal
 */
#[Package('core')]
class RoutingOverwriteMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, string|list<string>> $routing
     * @param array<string, string|list<string>> $overwrite
     */
    public function __construct(
        /**
         * @deprecated tag:v6.7.0 - Will be removed in v6.7.0.0. Use $overwrite instead
         */
        private readonly array $routing,
        private readonly array $overwrite
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->last(ReceivedStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        if ($this->hasTransportStamp($envelope)) {
            return $stack->next()->handle($envelope, $stack);
        }

        $overwrites = Feature::isActive('v6.7.0.0') ? $this->overwrite : $this->routing;

        $transports = $this->getTransports($envelope, $overwrites, true);

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
