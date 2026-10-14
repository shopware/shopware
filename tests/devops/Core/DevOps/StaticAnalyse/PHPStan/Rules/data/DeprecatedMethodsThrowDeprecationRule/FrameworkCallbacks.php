<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\Feature as MajorFeature;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\AbstractExtension;

/**
 * @deprecated tag:v6.8.0 - Removed subscriber
 */
class GuardedSubscriber implements EventSubscriberInterface, ResetInterface
{
    public static function getSubscribedEvents(): array
    {
        if (MajorFeature::isActive('v6.8.0.0') || MajorFeature::isActive('CACHE_REWORK')) {
            return [];
        }

        return ['event' => 'onEvent'];
    }

    public function onEvent(): void
    {
        MajorFeature::throwIfActive('v6.8.0.0', 'Removed subscriber');
    }

    public function reset(): void
    {
    }

    public function missingGuard(): void
    {
    }

    public function wrongFlag(): void
    {
        MajorFeature::throwIfActive('v6.9.0.0', 'Wrong major');
    }

    public function lateGuard(): void
    {
        $value = 1;
        MajorFeature::throwIfActive('v6.8.0.0', 'Too late');
    }
}

/**
 * @deprecated tag:v6.9.0 - Removed subscriber
 */
class WrongDiscoveryFlag implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        if (MajorFeature::isActive('v6.8.0.0')) {
            return [];
        }

        return ['event' => 'onEvent'];
    }
}

/**
 * @deprecated tag:v6.8.0 - Removed subscriber
 */
class NonemptyDiscovery implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        if (MajorFeature::isActive('v6.8.0.0')) {
            return ['event' => 'onEvent'];
        }

        return [];
    }
}

/**
 * @deprecated tag:v6.8.0 - Removed handler
 */
class NeutralHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return 0;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        return null;
    }

    public function extraPublicMethod(): void
    {
        MajorFeature::throwIfActive('v6.8.0.0', 'Removed handler');
    }
}

/**
 * @deprecated tag:v6.8.0 - Removed handler
 */
class GuardedHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        MajorFeature::throwIfActive('v6.8.0.0', 'Unsafe lifecycle guard');

        return 0;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if (MajorFeature::isActive('v6.8.0.0')) {
            return null;
        }

        return $e;
    }
}

/**
 * @deprecated tag:v6.8.0 - Removed extension
 */
class GuardedTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        if (MajorFeature::isActive('v6.8.0.0')) {
            return [];
        }

        return [];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function exposedFunction(): void
    {
        MajorFeature::throwIfActive('v6.8.0.0', 'Still needs a migration signal');
    }
}

/**
 * @deprecated tag:v6.8.0 - Removed resettable
 */
class UnsafeReset implements ResetInterface
{
    public function reset(): void
    {
        MajorFeature::triggerDeprecationOrThrow('v6.8.0.0', 'Unsafe lifecycle guard');
    }
}

/**
 * @deprecated tag:v6.8.0 - Removed listener
 */
class GuardedListener
{
    public function __invoke(): void
    {
        MajorFeature::throwIfActive('v6.8.0.0', 'Removed listener');
    }

    public function extraPublicMethod(): void
    {
    }
}
