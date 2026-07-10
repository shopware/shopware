<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Error;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
class ProviderValidationError extends Error
{
    /**
     * @var list<ErrorMessage>
     */
    protected array $errorMessages;

    public function __construct(
        protected string $id,
        protected string $provider,
        protected string $field,
        protected string $error,
        protected ?int $errorLine = null,
    ) {
        $message = new ErrorMessage();
        $message->assign([
            'message' => $error,
            'line' => $this->errorLine,
        ]);

        $this->errorMessages = [$message];
        $this->message = 'The export did not satisfy the provider requirements';

        parent::__construct($this->message);
    }

    public function getId(): string
    {
        return $this->getMessageKey() . $this->id . $this->provider . $this->field . ($this->errorLine ?? 'global');
    }

    public function getMessageKey(): string
    {
        return 'provider-validation-failed';
    }

    /**
     * @return array<string, int|string|null>
     */
    public function getParameters(): array
    {
        return [
            'provider' => $this->provider,
            'field' => $this->field,
            'error' => $this->error,
            'line' => $this->errorLine,
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
