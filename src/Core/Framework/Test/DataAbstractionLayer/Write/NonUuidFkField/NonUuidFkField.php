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

use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;

/**
 * @internal test class
 */
class NonUuidFkField extends FkField
{
    protected function getSerializerClass(): string
    {
        return NonUuidFkFieldSerializer::class;
    }
}
