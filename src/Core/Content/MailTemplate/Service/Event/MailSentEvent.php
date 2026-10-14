<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service\Event;

use Monolog\Level;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\Mail\Service\AbstractMailFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\ArrayType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\LogAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @phpstan-import-type MailNameCombination from AbstractMailFactory
 * @phpstan-import-type Contents from AbstractMailFactory
 */
#[Package('after-sales')]
class MailSentEvent extends Event implements LogAware, ScalarValuesAware, FlowEventAware
{
    final public const EVENT_NAME = 'mail.sent';

    /**
     * @param MailNameCombination $recipients
     * @param Contents $contents
     * @param array<string, mixed> $data the mail data passed to the mail service, e.g. `templateId` and `salesChannelId`
     * @param array<string, mixed> $templateData the template data the mail was rendered with, e.g. `eventName` and the order
     */
    public function __construct(
        private readonly string $subject,
        private readonly array $recipients,
        private readonly array $contents,
        private readonly Context $context,
        private readonly ?string $eventName = null,
        private readonly array $data = [],
        private readonly array $templateData = [],
        private readonly ?Email $message = null,
    ) {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(FlowMailVariables::SUBJECT, new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add(FlowMailVariables::CONTENTS, new ScalarValueType(ScalarValueType::TYPE_STRING), [EventDataCollection::HIDDEN_FROM_WEBHOOK => true])
            ->add(FlowMailVariables::RECIPIENTS, new ArrayType(new ScalarValueType(ScalarValueType::TYPE_STRING)));
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            FlowMailVariables::SUBJECT => $this->subject,
            FlowMailVariables::CONTENTS => $this->contents,
            FlowMailVariables::RECIPIENTS => $this->recipients,
        ];
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @return Contents
     */
    public function getContents(): array
    {
        return $this->contents;
    }

    /**
     * @return MailNameCombination
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    public function getMessage(): ?Email
    {
        return $this->message;
    }

    public function getEventName(): ?string
    {
        return $this->eventName;
    }

    public function getTemplateId(): ?string
    {
        $templateId = $this->data['templateId'] ?? null;

        return \is_string($templateId) ? $templateId : null;
    }

    public function getSalesChannelId(): ?string
    {
        $salesChannelId = $this->data['salesChannelId'] ?? null;

        return \is_string($salesChannelId) ? $salesChannelId : null;
    }

    public function getLogData(): array
    {
        return [
            'eventName' => $this->eventName,
            'subject' => $this->subject,
            'recipients' => $this->recipients,
            'contents' => $this->contents,
        ];
    }

    public function getLogLevel(): Level
    {
        return Level::Info;
    }
}
