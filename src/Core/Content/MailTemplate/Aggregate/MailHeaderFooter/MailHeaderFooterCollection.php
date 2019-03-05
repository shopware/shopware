<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MailHeaderFooterCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MailHeaderFooterEntity::class;
    }
}
