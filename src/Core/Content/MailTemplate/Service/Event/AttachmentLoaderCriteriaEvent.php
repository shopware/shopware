<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal (flag: FEATURE_NEXT_7530)
 * @package sales-channel
 */
#[Package('sales-channel')]
class AttachmentLoaderCriteriaEvent extends Event
{
    public const EVENT_NAME = 'mail.after.create.message';

    private Criteria $criteria;

    public function __construct(Criteria $criteria)
    {
        $this->criteria = $criteria;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }
}
