<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Event;

use Monolog\Logger;
use Shopware\Core\Content\Flow\Dispatching\Aware\NameAware;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Log\LogAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('sales-channel')]
class ProductExportLoggingEvent extends Event implements LogAware, MailAware, NameAware
{
    final public const NAME = 'product_export.log';

    /**
     * @var 100|200|250|300|400|500|550|600
     */
    private readonly int $logLevel;

    private string $name = self::NAME;

    /**
     * @internal
     *
     * @param 100|200|250|300|400|500|550|600|null $logLevel
     */
    public function __construct(
        private readonly Context $context,
        ?string $name,
        ?int $logLevel,
        private readonly ?\Throwable $throwable = null
    ) {
        $this->name = $name ?? self::NAME;
        $this->logLevel = $logLevel ?? Logger::DEBUG;
    }

    public function getThrowable(): ?\Throwable
    {
        return $this->throwable;
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

    public function getName(): string
    {
        return $this->name;
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

        return $logData;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('name', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getMailStruct(): MailRecipientStruct
    {
        throw new MailEventConfigurationException('Data for mailRecipientStruct not available.', self::class);
    }

    public function getSalesChannelId(): ?string
    {
        return null;
    }
}
