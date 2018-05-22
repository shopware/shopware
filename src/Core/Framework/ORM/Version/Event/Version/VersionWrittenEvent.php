<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Version\Event\Version;

use Shopware\Framework\ORM\Version\Definition\VersionDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
