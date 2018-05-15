<?php declare(strict_types=1);

namespace Shopware\System\Config\Event\ConfigForm;

use Shopware\System\Config\Definition\ConfigFormDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class ConfigFormDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'config_form.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigFormDefinition::class;
    }
}
