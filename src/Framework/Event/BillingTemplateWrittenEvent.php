<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class BillingTemplateWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 's_billing_template.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_billing_template';
    }
}
