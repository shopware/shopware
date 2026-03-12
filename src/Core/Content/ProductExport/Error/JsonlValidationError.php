<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Error;

use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class JsonlValidationError extends Error
{
    /**
     * @var list<ErrorMessage>
     */
    protected array $errorMessages;

    public function __construct(
        protected string $id,
        protected string $error
    ) {
        $message = new ErrorMessage();
        $message->assign([
            'message' => $error,
            'line' => 1,
            'column' => 1,
        ]);

        $this->errorMessages = [$message];
        $this->message = 'The export did not generate a valid JSON file';

        parent::__construct($this->message);
    }

    public function getId(): string
    {
        return $this->getMessageKey() . $this->id;
    }

    public function getMessageKey(): string
    {
        return 'json-validation-failed';
    }

    /**
     * @return array<string, string>
     */
    public function getParameters(): array
    {
        return ['error' => $this->error];
    }

    /**
     * @return list<ErrorMessage>
     */
    public function getErrorMessages(): array
    {
        return $this->errorMessages;
    }
}
