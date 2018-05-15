<?php declare(strict_types=1);

namespace Shopware\Api\Version\Event\VersionCommit;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Api\Version\Definition\VersionCommitDefinition;

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
