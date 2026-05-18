<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Queue\Message;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ProcessRunMessage implements AsyncMessageInterface
{
    public function __construct(
        public readonly Context $context,
        public readonly string $runId
    ) {
    }
}
