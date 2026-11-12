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
    public function testListsEveryUnmetRequirement(): void
    {
        $error = new UnmetRequirementError(
            new UnmetRequirement('SwagExample', 'public-access', 'Make the shop reachable from the internet'),
            new UnmetRequirement('SwagExample', 'secure-url', 'Use https for APP_URL'),
        );

        $violations = 'App "SwagExample" - Requirement "public-access": Make the shop reachable from the internet; '
            . 'App "SwagExample" - Requirement "secure-url": Use https for APP_URL';

        static::assertSame('The app requirements are not met: ' . $violations, $error->getMessage());
        static::assertSame(AppException::APP_REQUIREMENTS_NOT_MET, $error->getErrorCode());
        static::assertSame(['violations' => $violations], $error->getParameters());
    }
}
