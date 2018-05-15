<?php declare(strict_types=1);

namespace Shopware\Application\Application\Event\Application;

use Shopware\Application\Application\Definition\ApplicationDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class ApplicationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'application.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ApplicationDefinition::class;
    }
}
