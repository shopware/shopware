<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class MissingTranslationError extends Error
{
    private const KEY = 'manifest-missing-translation';

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

        $message = sprintf(
            "Missing translations for \"%s\":\n- %s",
            $xmlClassName,
            implode("\n- ", $validations)
        );

        parent::__construct($message);
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }
}
