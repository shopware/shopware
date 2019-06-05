<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Symfony\Component\EventDispatcher\Event;

class MailDispatchedEvent extends Event implements BusinessEventInterface
{
    public const EVENT_NAME = 'mail.dispatched';

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $subject;

    /**
     * @var array
     */
    private $contents;

    /**
     * @var array
     */
    private $recipients;

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
            // todo: type array?
            ->add('recipients', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('contents', new ScalarValueType(ScalarValueType::TYPE_STRING));
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

    public function getContents(): array
    {
        return $this->contents;
    }

    public function getRecipients(): array
    {
        return $this->recipients;
    }
}
