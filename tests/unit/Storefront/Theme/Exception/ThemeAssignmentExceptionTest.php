<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Theme\Exception\ThemeAssignmentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - reason: the tested class is superseded by ThemeException::themeAssignmentException - to be removed
 */
#[Package('discovery')]
#[CoversClass(ThemeAssignmentException::class)]
#[DisabledFeatures(['v6.8.0.0'])]
class ThemeAssignmentExceptionTest extends TestCase
{
    #[TestDox('the message lists theme and child theme assignments with resolved sales channel names')]
    public function testMessageFormatsAssignments(): void
    {
        $exception = new ThemeAssignmentException(
            'MyTheme',
            ['MyTheme' => ['sc-1']],
            ['MyChildTheme' => ['sc-2', 'sc-unknown']],
            ['sc-1' => 'Storefront', 'sc-2' => 'Headless', 'sc-unknown' => ''],
        );

        static::assertSame('THEME__THEME_ASSIGNMENT', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertStringContainsString('Unable to deactivate or uninstall theme "MyTheme".', $exception->getMessage());
        static::assertStringContainsString('"MyTheme" => "Storefront"', $exception->getMessage());
        // unresolvable sales channel ids fall back to the raw id
        static::assertStringContainsString('"MyChildTheme" => "Headless, sc-unknown"', $exception->getMessage());
    }
}
