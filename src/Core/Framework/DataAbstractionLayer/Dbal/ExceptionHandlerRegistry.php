<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Feature;

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

    /**
     * @internal (flag:FEATURE_NEXT_16640) - second parameter WriteCommand $command will be removed
     */
    public function matchException(\Exception $e, ?WriteCommand $command = null): ?\Exception
    {
        foreach ($this->getExceptionHandlers() as $priorityExceptionHandlers) {
            foreach ($priorityExceptionHandlers as $exceptionHandler) {
                if (!Feature::isActive('FEATURE_NEXT_16640')) {
                    $innerException = $exceptionHandler->matchException($e, $command);
                } else {
                    $innerException = $exceptionHandler->matchException($e);
                }
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
