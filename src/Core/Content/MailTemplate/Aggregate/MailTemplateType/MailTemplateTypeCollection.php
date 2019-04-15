<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MailTemplateTypeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MailTemplateTypeEntity::class;
    }
}
