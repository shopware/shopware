<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\fixtures;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final class DummyHandler
{
    private object $lastMessage;

    private ?\Throwable $exceptionToThrow = null;

    public function __invoke(FooMessage $message): void
    {
        $this->lastMessage = $message;

        if ($this->exceptionToThrow) {
            throw $this->exceptionToThrow;
        }
    }

    public function getLastMessage(): object
    {
        return $this->lastMessage;
    }

    public function willThrowException(\Throwable $e): self
    {
        $this->exceptionToThrow = $e;

        return $this;
    }
}
