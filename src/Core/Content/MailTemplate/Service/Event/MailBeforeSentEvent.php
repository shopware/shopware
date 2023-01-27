<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service\Event;

use Monolog\Logger;
use Shopware\Core\Content\Flow\Dispatching\Aware\DataAware;
use Shopware\Core\Content\Flow\Dispatching\Aware\MessageAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\ArrayType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Log\LogAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('sales-channel')]
class MailBeforeSentEvent extends Event implements LogAware, DataAware, MessageAware
{
    final public const EVENT_NAME = 'mail.after.create.message';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly array $data,
        private readonly Email $message,
        private readonly Context $context,
        private readonly ?string $eventName = null
    ) {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('data', new ArrayType(new ScalarValueType(ScalarValueType::TYPE_STRING)))
            ->add('message', new ObjectType());
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getMessage(): Email
    {
        return $this->message;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLogData(): array
    {
        $data = $this->data;
        unset($data['binAttachments']);

        return [
            'data' => $data,
            'eventName' => $this->eventName,
            'message' => $this->message,
        ];
    }

    public function getLogLevel(): int
    {
        return Logger::INFO;
    }
}
