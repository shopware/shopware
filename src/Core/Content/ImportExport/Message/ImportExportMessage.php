<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Message;

use Shopware\Core\Framework\Context;

class ImportExportMessage
{
    private Context $context;

    private string $logId;

    private string $activity;

    private int $offset = 0;

    public function __construct(Context $context, string $logId, string $activity, int $offset = 0)
    {
        $this->context = $context;
        $this->logId = $logId;
        $this->activity = $activity;
        $this->offset = $offset;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getLogId(): string
    {
        return $this->logId;
    }

    public function getActivity(): string
    {
        return $this->activity;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }
}
