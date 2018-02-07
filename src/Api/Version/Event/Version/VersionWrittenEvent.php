<?php declare(strict_types=1);

namespace Shopware\Api\Version\Event\Version;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Version\Definition\VersionDefinition;

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
