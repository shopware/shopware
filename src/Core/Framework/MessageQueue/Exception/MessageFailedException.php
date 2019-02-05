<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Exception;

class MessageFailedException extends \RuntimeException
{
    /**
     * @var object
     */
    private $originalMessage;

    /**
     * @var string
     */
    private $handlerClass;

    public function __construct(object $originalMessage, string $handlerClass, \Throwable $previous)
    {
        $this->originalMessage = $originalMessage;
        $this->handlerClass = $handlerClass;

        parent::__construct(
            sprintf('The handler "%s" threw a "%s" for message "%s"',
                $handlerClass, get_class($previous), get_class($originalMessage)
            ),
            0,
            $previous
        );
    }

    public function getOriginalMessage(): object
    {
        return $this->originalMessage;
    }

    public function getHandlerClass(): string
    {
        return $this->handlerClass;
    }
}
