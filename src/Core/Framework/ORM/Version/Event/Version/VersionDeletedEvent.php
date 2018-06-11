<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Version\Event\Version;

use Shopware\Core\Framework\ORM\Version\Definition\VersionDefinition;
use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
