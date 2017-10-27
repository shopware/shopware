<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class MultiEditBackupWrittenEvent extends WrittenEvent
{
    const NAME = 's_multi_edit_backup.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_multi_edit_backup';
    }
}
