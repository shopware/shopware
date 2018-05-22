<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Version\Event\VersionCommit;

use Shopware\Framework\ORM\Version\Definition\VersionCommitDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
