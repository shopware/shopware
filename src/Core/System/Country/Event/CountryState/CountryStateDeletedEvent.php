<?php declare(strict_types=1);

namespace Shopware\System\Country\Event\CountryState;

use Shopware\System\Country\Definition\CountryStateDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class CountryStateDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'country_state.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryStateDefinition::class;
    }
}
