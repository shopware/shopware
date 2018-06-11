<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Version\Event\VersionCommit;

use Shopware\Core\Framework\ORM\Version\Definition\VersionCommitDefinition;
use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

class VersionCommitDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'version_commit.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return VersionCommitDefinition::class;
    }
}
