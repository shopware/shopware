<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('framework')]
final class SalesChannelFileTemplateResolveEvent extends Event
{
    public function __construct(public readonly string $salesChannelId)
    {
    }
}
