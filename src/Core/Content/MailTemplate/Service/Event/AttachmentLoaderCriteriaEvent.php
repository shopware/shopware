<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service\Event;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('sales-channel')]
class AttachmentLoaderCriteriaEvent extends Event
{
    final public const EVENT_NAME = 'mail.after.create.message';

    public function __construct(private readonly Criteria $criteria)
    {
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }
}
