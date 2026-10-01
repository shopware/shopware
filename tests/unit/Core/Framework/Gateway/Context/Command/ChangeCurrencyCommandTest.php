<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeCurrencyCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChangeCurrencyCommand::class)]
class ChangeCurrencyCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = ChangeCurrencyCommand::createFromPayload(['iso' => 'EUR']);

        static::assertSame('context_change-currency', $command::getDefaultKeyName());
        static::assertSame('EUR', $command->iso);
    }
}
