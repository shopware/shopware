<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service\Event;

use Monolog\Logger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\ArrayType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Log\LogAwareBusinessEventInterface;
use Symfony\Contracts\EventDispatcher\Event;

class MailBeforeSendEvent extends Event implements BusinessEventInterface, LogAwareBusinessEventInterface
{
    public const EVENT_NAME = 'mail.before.send';

    /**
     * @var array
     */
    private $data;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var array
     */
    private $templateData;

    public function __construct(array $data, Context $context, array $templateData = [])
    {
        $this->data = $data;
        $this->context = $context;
        $this->templateData = $templateData;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('data', new ArrayType(new ScalarValueType(ScalarValueType::TYPE_STRING)))
            ->add('templateData', new ArrayType(new ScalarValueType(ScalarValueType::TYPE_STRING)));
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getData(): Context
    {
        return $this->data;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    public function getLogData(): array
    {
        return [
            'subject' => $this->subject,
            'recipients' => $this->recipients,
            'contents' => $this->contents,
        ];
    }

    public function getLogLevel(): int
    {
        return Logger::INFO;
    }
}
