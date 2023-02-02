<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Exception;

class ExecuteSequenceException extends \Exception
{
    private string $flowId;

    private string $sequenceId;

    public function __construct(string $flowId, string $sequenceId, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->flowId = $flowId;
        $this->sequenceId = $sequenceId;
    }

    public function getFlowId(): string
    {
        return $this->flowId;
    }

    public function getSequenceId(): string
    {
        return $this->sequenceId;
    }
}
