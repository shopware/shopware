<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;

class ExceptionHandlerRegistry
{
    /**
     * @var array
     */
    protected $exceptionHandlers = [];

    public function __construct(iterable $exceptionHandlers)
    {
        foreach ($exceptionHandlers as $exceptionHandler) {
            $this->add($exceptionHandler);
        }
    }

    public function add(ExceptionHandlerInterface $exceptionHandler): void
    {
        $this->exceptionHandlers[$exceptionHandler->getPriority()][] = $exceptionHandler;
    }

    public function matchException(\Exception $e, WriteCommand $command): ?\Exception
    {
        foreach ($this->getExceptionHandlers() as $priorityExceptionHandlers) {
            foreach ($priorityExceptionHandlers as $exceptionHandler) {
                $innerException = $exceptionHandler->matchException($e, $command);
                if ($innerException instanceof \Exception) {
                    return $innerException;
                }
            }
        }

        return null;
    }

    public function getExceptionHandlers(): array
    {
        return $this->exceptionHandlers;
    }
}
