<?php declare(strict_types=1);

namespace Shopware\Api\Version\Event\VersionCommit;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Api\Version\Definition\VersionCommitDefinition;

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
