<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

/**
 * Tag for events that can be used in the action/action system
 *
 * @feature-deprecated (FEATURE_NEXT_8225) tag:v6.5.0 - Will be removed in v6.5.0, use WebhookAware instead.
 */
interface BusinessEventInterface extends WebhookAware
{
}
