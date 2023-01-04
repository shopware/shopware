<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MailHeaderFooterEntity>
 */
#[Package('sales-channel')]
class MailHeaderFooterCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'mail_template_header_footer_collection';
    }

    protected function getExpectedClass(): string
    {
        return MailHeaderFooterEntity::class;
    }
}
