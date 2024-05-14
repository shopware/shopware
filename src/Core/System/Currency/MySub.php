<?php

namespace Shopware\Core\System\Currency;

use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Fk;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Primary;
use Shopware\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;

#[Entity(name: 'my_sub')]
class MySub extends EntityStruct
{
    #[Primary]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[Fk(entity: 'my_entity')]
    public string $myEntityId;

    #[Field(type: FieldType::STRING)]
    public string $number;
}
