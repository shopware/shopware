<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service\Event;

use Monolog\Logger;
use Shopware\Core\Content\Flow\Dispatching\Aware\NameAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\LogAware;
use Symfony\Contracts\EventDispatcher\Event;

class MailErrorEvent extends Event implements LogAware, FlowEventAware, BusinessEventInterface, NameAware
{
    public const NAME = 'mail.sent.error';

    private Context $context;

    /**
     * @var 100|200|250|300|400|500|550|600
     */
    private int $logLevel;

    private ?\Throwable $throwable;

    private ?string $message;

    private ?string $template;

    /**
     * @var array<string, mixed>
     */
    private ?array $templateData;

    /**
     * @param 100|200|250|300|400|500|550|600|null $logLevel
     * @param array<string, mixed> $templateData
     */
    public function __construct(
        Context $context,
        ?int $logLevel,
        ?\Throwable $throwable = null,
        ?string $message = null,
        ?string $template = null,
        ?array $templateData = []
    ) {
        $this->templateData = $templateData;
        $this->template = $template;
        $this->message = $message;
        $this->throwable = $throwable;
        $this->logLevel = $logLevel ?? Logger::DEBUG;
        $this->context = $context;
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

    /**
     * @return 100|200|250|300|400|500|550|600
     */
    public function getLogLevel(): int
    {
        return $this->logLevel;
    }

    /**
     * @return array<string, mixed>
     */
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
