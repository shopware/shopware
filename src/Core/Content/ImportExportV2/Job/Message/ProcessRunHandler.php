<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Message;

use Shopware\Core\Content\ImportExportV2\ImportExportV2Exception;
use Shopware\Core\Content\ImportExportV2\Job\Service\RunService;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ProcessRunHandler
{
    public function __construct(private readonly RunService $runService)
    {
    }

    public function __invoke(ProcessRunMessage $message): void
    {
        try {
            $this->runService->process($message->getRunId(), $message->getContext());
        } catch (ImportExportV2Exception $exception) {
            throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
        }
    }
}
