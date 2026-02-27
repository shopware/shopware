<?php declare(strict_types=1);

namespace Shopware\Core\Content\RevocationRequest\Event;

use Shopware\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('after-sales')]
final class RevocationRequestEvent extends Event implements SalesChannelAware, MailAware, ScalarValuesAware, FlowEventAware
{
    public const EVENT_NAME = 'revocation_request.sent';

    /**
     * @var array<int|string, mixed>
     */
    private readonly array $formData;

    public function __construct(
        private readonly Context $context,
        private readonly string $salesChannelId,
        private readonly MailRecipientStruct $recipients,
        DataBag $formDataBag
    ) {
        $this->formData = $formDataBag->all();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(FlowMailVariables::REVOCATION_REQUEST_FORM_DATA, new ObjectType());
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return $this->recipients;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getValues(): array
    {
        return [
            FlowMailVariables::REVOCATION_REQUEST_FORM_DATA => $this->formData,
        ];
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
