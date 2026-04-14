<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Hookable;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;

/**
 * This interface describes how additional webhook event names and their
 * required privileges are provided for app manifest validation.
 *
 * Implementations are discovered through the `shopware.hookable_event.describer` tag.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
interface HookableEventDescriber
{
    /**
     * @return list<HookableEventDescription>
     */
    public function describe(Manifest $manifest): array;
}
