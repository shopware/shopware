<?php declare(strict_types=1);

namespace Shopware\Api\Version\Event\VersionCommit;

use Shopware\Api\Version\Definition\VersionCommitDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

class VersionCommitWrittenEvent extends WrittenEvent
{
    public const NAME = 'version_commit.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return VersionCommitDefinition::class;
    }
}
