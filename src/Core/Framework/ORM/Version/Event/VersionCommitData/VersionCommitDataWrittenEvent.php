<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Version\Event\VersionCommitData;

use Shopware\Framework\ORM\Version\Definition\VersionCommitDataDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class VersionCommitDataWrittenEvent extends WrittenEvent
{
    public const NAME = 'version_commit_data.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return VersionCommitDataDefinition::class;
    }
}
