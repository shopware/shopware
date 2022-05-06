<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.5.0 - Will be removed
 */
class DocumentOrderCriteriaEvent extends Event
{
    /**
     * @var Criteria
     */
    private $criteria;

    /**
     * @var Context
     */
    private $context;

    public function __construct(Criteria $criteria, Context $context)
    {
        $this->criteria = $criteria;
        $this->context = $context;
    }

    public function getCriteria(): Criteria
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->criteria;
    }

    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->context;
    }
}
