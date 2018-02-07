<?php declare(strict_types=1);

namespace Shopware\Api\Version\Event\VersionCommitData;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Version\Definition\VersionCommitDataDefinition;

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
