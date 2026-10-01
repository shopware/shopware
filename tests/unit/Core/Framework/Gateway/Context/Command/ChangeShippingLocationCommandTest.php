<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeShippingLocationCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChangeShippingLocationCommand::class)]
class ChangeShippingLocationCommandTest extends TestCase
{
    public function testCommandEmpty(): void
    {
        $command = ChangeShippingLocationCommand::createFromPayload();

        static::assertSame('context_change-shipping-location', $command::getDefaultKeyName());
        static::assertNull($command->countryIso);
        static::assertNull($command->countryStateIso);
    }

    public function testCommand(): void
    {
        $command = ChangeShippingLocationCommand::createFromPayload(['countryIso' => 'DE', 'countryStateIso' => 'DE-BY']);

        static::assertSame('context_change-shipping-location', $command::getDefaultKeyName());
        static::assertSame('DE', $command->countryIso);
        static::assertSame('DE-BY', $command->countryStateIso);
    }
}
