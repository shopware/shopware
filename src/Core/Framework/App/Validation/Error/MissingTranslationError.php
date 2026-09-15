<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class MissingTranslationError implements Error
{
    private readonly string $message;

    /**
     * @param array<string, array<string>> $missingTranslations
     */
    public function __construct(
        string $xmlElementClass,
        array $missingTranslations
    ) {
        $path = explode('\\', $xmlElementClass);
        $xmlClassName = array_pop($path);

        $validations = [];
        foreach ($missingTranslations as $field => $missingTranslation) {
            $validations[] = $field . ': ' . implode(', ', $missingTranslation);
        }

        $this->message = \sprintf(
            "Missing translations for \"%s\":\n- %s",
            $xmlClassName,
            implode("\n- ", $validations)
        );
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getErrorCode(): string
    {
        return AppException::VALIDATION_FAILED;
    }

    public function getParameters(): array
    {
        return [];
    }

    public function isBlocking(): bool
    {
        return false;
    }
}
