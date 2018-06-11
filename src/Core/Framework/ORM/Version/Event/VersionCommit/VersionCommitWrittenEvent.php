<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Version\Event\VersionCommit;

use Shopware\Core\Framework\ORM\Version\Definition\VersionCommitDefinition;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
