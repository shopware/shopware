<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class RunSystemChecks implements AsyncMessageInterface
{
    public function __construct(public ?string $check = null)
    {
    }
}
