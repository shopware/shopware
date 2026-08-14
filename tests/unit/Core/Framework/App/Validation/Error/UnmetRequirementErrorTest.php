<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Validation\Error\UnmetRequirementError;
use Shopware\Core\Framework\App\Validation\Requirements\UnmetRequirement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UnmetRequirementError::class)]
class UnmetRequirementErrorTest extends TestCase
{
    public function testError(): void
    {
        $error = new UnmetRequirementError(
            new UnmetRequirement('MyApp', 'https', 'Use HTTPS'),
            new UnmetRequirement('MyApp', 'public-access', 'Expose the app server')
        );

        static::assertSame(
            'The app requirements are not met: App "MyApp" - Requirement "https": Use HTTPS; App "MyApp" - Requirement "public-access": Expose the app server',
            $error->getMessage()
        );
        static::assertSame(AppException::APP_REQUIREMENTS_NOT_MET, $error->getErrorCode());
        static::assertSame(
            ['violations' => 'App "MyApp" - Requirement "https": Use HTTPS; App "MyApp" - Requirement "public-access": Expose the app server'],
            $error->getParameters()
        );
        static::assertFalse($error->isBlocking());
    }
}
