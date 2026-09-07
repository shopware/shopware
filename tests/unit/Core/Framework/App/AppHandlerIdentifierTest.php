<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppHandlerIdentifier;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppHandlerIdentifier::class)]
class AppHandlerIdentifierTest extends TestCase
{
    public function testReturnsPrefix(): void
    {
        static::assertSame('app\\', AppHandlerIdentifier::prefix());
    }

    public function testBuildsIdentifier(): void
    {
        static::assertSame('app\\ExampleApp_payment', AppHandlerIdentifier::build('ExampleApp', 'payment'));
    }
}
