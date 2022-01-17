<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service\Event;

use Monolog\Logger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\LogAware;
use Symfony\Contracts\EventDispatcher\Event;

class MailErrorEvent extends Event implements LogAware, FlowEventAware
{
    public const NAME = 'mail.sent.error';

    private $context;

    /**
     * @var 100|200|250|300|400|500|550|600
     */
    private $logLevel;

    private ?\Throwable $throwable = null;

    private ?string $message = null;

    /**
     * @param 100|200|250|300|400|500|550|600|null $logLevel
     */
    public function __construct(
        Context $context,
        ?int $logLevel,
        ?\Throwable $throwable = null,
        ?string $message = null
    ) {
        $this->context = $context;
        $this->logLevel = $logLevel ?? Logger::DEBUG;
        $this->throwable = $throwable;
        $this->message = $message;
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
}
