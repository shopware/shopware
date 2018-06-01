<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Version\Event\Version;

use Shopware\Core\Framework\ORM\Version\Definition\VersionDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

class VersionWrittenEvent extends WrittenEvent
{
    public const NAME = 'version.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return VersionDefinition::class;
    }
}
