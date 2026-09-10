<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Hookable;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;

/**
 * Describes the webhook events an area of the system offers to apps, and which of them
 * a given app may subscribe to.
 *
 * The two methods are read as a pair. Comparing them is how the app-system tells an event
 * that does not exist on this Shopware version apart from one that exists but is withheld
 * from this app: the former is reported and still installs, so a single manifest can target
 * several versions; the latter refuses the installation.
 *
 * A describer never states why it withholds an event, and the app-system never asks.
 *
 * Implementations are discovered through the `shopware.hookable_event.describer` tag.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
interface HookableEventDescriber
{
    /**
     * Every event this describer offers on the running system, regardless of who is asking.
     *
     * @return list<HookableEventDescription>
     */
    public function describe(): array;

    /**
     * The events the given manifest is allowed to subscribe to.
     *
     * May return events describe() does not, for events the manifest itself brings into
     * being: an app declaring a consent may subscribe to that consent's events before it
     * is installed.
     *
     * May omit events describe() does return, which marks them as restricted for this
     * manifest and refuses an installation that subscribes to them. Omit only what no
     * version of Shopware would permit this manifest to receive.
     *
     * @return list<HookableEventDescription>
     */
    public function describePermittedFor(Manifest $manifest): array;
}
