<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service\Event;

use Monolog\Logger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\ArrayType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Log\LogAwareBusinessEventInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\EventDispatcher\Event;

class MailBeforeSentEvent extends Event implements BusinessEventInterface, LogAwareBusinessEventInterface
{
    public const EVENT_NAME = 'mail.after.create.message';

    /**
     * @var array
     */
    private $data;

    /**
     * @var \Swift_Message|Email
     *
     * @feature-deprecated flag:FEATURE_NEXT_12246 remove \Swift_Message Annotation on Feature Release
     */
    private $message;

    /**
     * @var Context
     */
    private $context;

    /**
     * @feature-deprecated flag:FEATURE_NEXT_12246 set TypeHint to Email on Feature Release
     */
    public function __construct(array $data, /*\Swift_Message|Email*/ $message, Context $context)
    {
        /* @feature-deprecated flag:FEATURE_NEXT_12246 remove type check when TypeHint is implemented */
        if (!($message instanceof \Swift_Message) && !($message instanceof Email)) {
            throw new \InvalidArgumentException(
                'second argument $message has to be of type \Swift_Message or Symfony\Component\Mime\Email'
            );
        }

        $this->data = $data;
        $this->message = $message;
        $this->context = $context;
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

    /**
     * @feature-deprecated flag:FEATURE_NEXT_12246 set ReturnType to Email on Feature Release
     *
     * @return \Swift_Message|Email
     */
    public function getMessage()
    {
        return $this->message;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getLogData(): array
    {
        return [
            'data' => $this->data,
            'message' => $this->message,
        ];
    }

    public function getLogLevel(): int
    {
        return Logger::INFO;
    }
}
