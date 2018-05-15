<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Event\CustomerGroupTranslation;

use Shopware\Checkout\Customer\Definition\CustomerGroupTranslationDefinition;
use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;

class CustomerGroupTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'customer_group_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CustomerGroupTranslationDefinition::class;
    }
}
