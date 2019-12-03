<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Event;

use Monolog\Logger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Log\LogAwareBusinessEventInterface;
use Symfony\Contracts\EventDispatcher\Event;

class ProductExportLoggingEvent extends Event implements BusinessEventInterface, LogAwareBusinessEventInterface
{
    public const NAME = 'product_export.log';

    /** @var Context */
    private $context;

    /** @var int */
    private $logLevel = Logger::DEBUG;

    /** @var \Throwable */
    private $throwable;

    /** @var string */
    private $name = self::NAME;

    public function __construct(
        Context $context,
        ?string $name,
        ?int $logLevel,
        ?\Throwable $throwable = null
    ) {
        $this->context = $context;
        $this->name = $name;
        $this->logLevel = $logLevel;
        $this->throwable = $throwable;
    }

    public function getThrowable(): ?\Throwable
    {
        return $this->throwable;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getLogLevel(): int
    {
        return $this->logLevel;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLogData(): array
    {
        $logData = [];

        if ($this->getThrowable()) {
            $throwable = $this->getThrowable();
            $logData['exception'] = (string) $throwable;
        }

        return $logData;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('name', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }
}
