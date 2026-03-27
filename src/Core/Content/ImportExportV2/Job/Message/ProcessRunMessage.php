<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Message;

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
        private readonly Context $context,
        private readonly string $runId
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getRunId(): string
    {
        return $this->runId;
    }
}
