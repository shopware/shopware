<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Tracking;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Marker event used to evaluate the Agentic Commerce Danger rule on test PRs.
 *
 * @codeCoverageIgnore
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
final class AgenticCommerceEvaluationEvent extends Event
{
}
