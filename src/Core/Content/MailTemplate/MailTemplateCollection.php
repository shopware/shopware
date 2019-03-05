<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MailTemplateCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MailTemplateEntity::class;
    }
}
