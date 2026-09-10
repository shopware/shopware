<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Validation\Error\ConfigurationError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConfigurationError::class)]
class ConfigurationErrorTest extends TestCase
{
    public function testError(): void
    {
        $error = new ConfigurationError(['sw-custom-one', 'sw-custom-two'], 'MyApp');

        $message = "The following custom components are not allowed to be used in app configuration:\n"
            . "- sw-custom-one\n"
            . '- sw-custom-two';

        static::assertSame($message, $error->getMessage());
        static::assertSame(AppException::INVALID_CONFIGURATION, $error->getErrorCode());
        static::assertSame(['appName' => 'MyApp', 'error' => $message], $error->getParameters());
        static::assertTrue($error->isBlocking());
    }
}
