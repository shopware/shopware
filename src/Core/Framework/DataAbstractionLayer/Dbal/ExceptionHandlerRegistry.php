<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface;

class ExceptionHandlerRegistry
{
    /**
     * @var ExceptionHandlerInterface[]
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
        $this->exceptionHandlers[] = $exceptionHandler;
    }

    public function matchException(\Exception $e, WriteCommandInterface $command): ?\Exception
    {
        foreach ($this->getExceptionHandlers() as $exceptionHandler) {
            $innerException = $exceptionHandler->matchException($e, $command);
            if ($innerException instanceof \Exception) {
                return $innerException;
            }
        }

        return null;
    }

    /**
     * @return ExceptionHandlerInterface[]
     */
    public function getExceptionHandlers(): array
    {
        return $this->exceptionHandlers;
    }
}
