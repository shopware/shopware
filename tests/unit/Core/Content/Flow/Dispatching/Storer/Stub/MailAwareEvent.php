<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer\Stub;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class MailAwareEvent implements FlowEventAware, MailAware
{
    public function getMailStruct(): MailRecipientStruct
    {
        return new MailRecipientStruct([]);
    }

    public function getSalesChannelId(): ?string
    {
        return null;
    }

    public function getName(): string
    {
        return 'test';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('timezone', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }
}
