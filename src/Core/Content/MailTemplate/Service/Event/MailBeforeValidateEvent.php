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

class MailBeforeValidateEvent extends Event implements BusinessEventInterface, LogAwareBusinessEventInterface
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

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @param string $value
     *
     * @deprecated tag:v6.5.0 The parameter $value will be native typed
     */
    public function addData(string $key, /*string */$value): void
    {
        $this->data[$key] = $value;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    public function setTemplateData(array $templateData): void
    {
        $this->templateData = $templateData;
    }

    /**
     * @param string $value
     *
     * @deprecated tag:v6.5.0 The parameter $value will be native typed
     */
    public function addTemplateData(string $key, /*string */$value): void
    {
        $this->templateData[$key] = $value;
    }

    public function getLogData(): array
    {
        return [
            'data' => $this->data,
            'templateData' => $this->templateData,
        ];
    }

    public function getLogLevel(): int
    {
        return Logger::INFO;
    }
}
