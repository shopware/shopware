<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
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
        if (!$event instanceof MailAware) {
            return $stored;
        }

        $stored[MailAware::TIMEZONE] = $this->getTimezone();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(MailAware::TIMEZONE)) {
            return;
        }

        $storable->setData(MailAware::TIMEZONE, $storable->getStore(MailAware::TIMEZONE));
    }

    private function getTimezone(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return 'UTC';
        }

        $timezone = (string) $request->cookies->get(self::TIMEZONE_COOKIE);

        if (!$timezone || !\in_array($timezone, timezone_identifiers_list(), true)) {
            // Default will be UTC @see https://symfony.com/doc/current/reference/configuration/twig.html#timezone
            return 'UTC';
        }

        return $timezone;
    }
}
