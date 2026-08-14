<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Validation\Error\MissingTranslationError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MissingTranslationError::class)]
class MissingTranslationErrorTest extends TestCase
{
    public function testError(): void
    {
        $error = new MissingTranslationError(Metadata::class, ['label' => ['de-DE'], 'description' => ['de-DE', 'nl-NL']]);

        static::assertSame(
            "Missing translations for \"Metadata\":\n- label: de-DE\n- description: de-DE, nl-NL",
            $error->getMessage()
        );
        static::assertSame(AppException::VALIDATION_FAILED, $error->getErrorCode());
        static::assertSame([], $error->getParameters());
        static::assertFalse($error->isBlocking());
    }
}
