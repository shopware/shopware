<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service\Event;

use Monolog\Logger;
use Shopware\Core\Content\Flow\Dispatching\Aware\ContentsAware;
use Shopware\Core\Content\Flow\Dispatching\Aware\RecipientsAware;
use Shopware\Core\Content\Flow\Dispatching\Aware\SubjectAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\ArrayType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Log\LogAware;
use Symfony\Contracts\EventDispatcher\Event;

class MailSentEvent extends Event implements BusinessEventInterface, LogAware, SubjectAware, ContentsAware, RecipientsAware
{
    public const EVENT_NAME = 'mail.sent';

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $subject;

    /**
     * @var array<string, mixed>
     */
    private $contents;

    /**
     * @var array<string, mixed>
     */
    private $recipients;

    /**
     * @param array<string, mixed> $recipients
     * @param array<string, mixed> $contents
     */
    public function __construct(string $subject, array $recipients, array $contents, Context $context)
    {
        $this->subject = $subject;
        $this->recipients = $recipients;
        $this->contents = $contents;
        $this->context = $context;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('subject', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('contents', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('recipients', new ArrayType(new ScalarValueType(ScalarValueType::TYPE_STRING)));
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
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
     * @return array<string, mixed>
     */
    public function getContents(): array
    {
        return $this->contents;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    /**
     * @return array<string, mixed>
     */
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
