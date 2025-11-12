<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Hookable;

use Shopware\Core\Framework\Log\Package;

/**
 * Marker interface that EntityDefinitions can implement to automatically be tagged as hookable.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
interface HookableEntityInterface
{
}
