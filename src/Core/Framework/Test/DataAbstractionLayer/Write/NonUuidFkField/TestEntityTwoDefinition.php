<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write\NonUuidFkField;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal test class
 */
class TestEntityTwoDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_entity_two';
    }

    public function since(): ?string
    {
        return null;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new NonUuidFkField('test_entity_one_technical_name', 'testEntityOneTechnicalName', TestEntityOneDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('testEntityOne', 'test_entity_one_technical_name', TestEntityOneDefinition::class, 'technical_name'),
        ]);
    }
}
