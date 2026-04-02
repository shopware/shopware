<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Error;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
class JsonlValidationError extends Error
{
    /**
     * @var list<ErrorMessage>
     */
    protected array $errorMessages;

    public function __construct(
        protected string $id,
        protected string $error,
        protected int $line = 1,
    ) {
        $message = new ErrorMessage();
        $message->assign([
            'message' => $error,
            'line' => $this->line,
        ]);

        $this->errorMessages = [$message];
        $this->message = 'The export did not generate a valid JSONL file';

        parent::__construct($this->message);
    }

    public function getId(): string
    {
        return $this->getMessageKey() . $this->id;
    }

    public function getMessageKey(): string
    {
        return 'jsonl-validation-failed';
    }

    /**
     * @return array<string, int|string>
     */
    public function getParameters(): array
    {
        return [
            'error' => $this->error,
            'line' => $this->line,
        ];
    }

    /**
     * @return list<ErrorMessage>
     */
    public function getErrorMessages(): array
    {
        return $this->errorMessages;
    }
}
