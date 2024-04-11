<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service\Event;

use Monolog\Level;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\LogAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('buyers-experience')]
class MailErrorEvent extends Event implements LogAware, ScalarValuesAware, FlowEventAware
{
    final public const NAME = 'mail.sent.error';

    private readonly Level $logLevel;

    /**
     * @param array<string, mixed> $templateData
     */
    public function __construct(
        private readonly Context $context,
        ?Level $logLevel = Level::Debug,
        private readonly ?\Throwable $throwable = null,
        private readonly ?string $message = null,
        private readonly ?string $template = null,
        private readonly ?array $templateData = []
    ) {
        $this->logLevel = $logLevel ?? Level::Debug;
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [FlowMailVariables::EVENT_NAME => self::NAME];
    }

    public function getThrowable(): ?\Throwable
    {
        return $this->throwable;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getLogLevel(): Level
    {
        return $this->logLevel;
    }

    public function getLogData(): array
    {
        $logData = [];

        if ($this->getThrowable()) {
            $throwable = $this->getThrowable();
            $logData['exception'] = (string) $throwable;
        }

        if ($this->message) {
            $logData['message'] = $this->message;
        }

        if ($this->template) {
            $logData['template'] = $this->template;
        }

        $logData['eventName'] = null;

        if ($this->templateData) {
            $logData['templateData'] = $this->templateData;
            $logData['eventName'] = $this->templateData['eventName'] ?? null;
        }

        return $logData;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('name', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    /**
     * @return mixed[]|null
     */
    public function getTemplateData(): ?array
    {
        return $this->templateData;
    }
}
