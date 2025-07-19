<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeLanguageCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChangeLanguageCommand::class)]
class ChangeLanguageCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = ChangeLanguageCommand::createFromPayload(['iso' => 'de-DE']);

        static::assertSame('context_change-language', $command::getDefaultKeyName());
        static::assertSame('de-DE', $command->iso);
    }
}
