<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\App\Validation\AppValidatorTest
 */
#[Package('framework')]
class MissingTranslationError implements Error
{
    public const CODE = 'FRAMEWORK__APP_MISSING_TRANSLATIONS';

    /**
     * @param list<string> $locales
     */
    public function __construct(private readonly array $locales)
    {
    }

    public function getMessage(): string
    {
        return \sprintf('Missing label translations: %s', implode(', ', $this->locales));
    }

    public function getErrorCode(): string
    {
        return self::CODE;
    }

    public function getParameters(): array
    {
        return [];
    }

    public function getSeverity(): Severity
    {
        return Severity::Notice;
    }
}
