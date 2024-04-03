<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service\Event;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.7.0 - Will be removed as the service dispatching this event, will be removed
 */
#[Package('services-settings')]
class AttachmentLoaderCriteriaEvent extends Event
{
    final public const EVENT_NAME = 'mail.after.create.message';

    public function __construct(private readonly Criteria $criteria)
    {
    }

    public function getCriteria(): Criteria
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.7.0.0')
        );

        return $this->criteria;
    }
}
