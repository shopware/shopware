<?php declare(strict_types=1);

namespace Shopware\Api\Version\Event\Version;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Api\Version\Definition\VersionDefinition;

class VersionDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'version.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return VersionDefinition::class;
    }
}
