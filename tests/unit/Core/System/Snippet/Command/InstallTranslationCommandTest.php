<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\InstallTranslationCommand;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(InstallTranslationCommand::class)]
class InstallTranslationCommandTest extends TestCase
{
    protected function setUp(): void
    {
        static::assertTrue(false);
    }
}
