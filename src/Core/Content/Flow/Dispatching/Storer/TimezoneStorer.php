<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\TimezoneAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\RequestStack;

#[Package('after-sales')]
class TimezoneStorer extends FlowStorer
{
    final public const TIMEZONE_COOKIE = 'timezone';

    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof TimezoneAware) {
            return $stored;
        }

        $stored[TimezoneAware::TIMEZONE] = $this->getTimezone();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(TimezoneAware::TIMEZONE)) {
            return;
        }

        $storable->setData(TimezoneAware::TIMEZONE, $storable->getStore(TimezoneAware::TIMEZONE));
    }

    private function getTimezone(): string
    {
        $timezone = (string) $this->requestStack->getCurrentRequest()?->cookies->get(self::TIMEZONE_COOKIE);

        if ($timezone === 'UTC' || !$timezone || !\in_array($timezone, timezone_identifiers_list(), true)) {
            // Default will be UTC @see https://symfony.com/doc/current/reference/configuration/twig.html#timezone
            return 'UTC';
        }

        return $timezone;
    }
}
