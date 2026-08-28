<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerVatIdCountrySubscriber;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerVatIdCountrySubscriber::class)]
class CustomerVatIdCountrySubscriberTest extends TestCase
{
    public function testSubscribesToWriteEvent(): void
    {
        static::assertSame(
            [EntityWriteEvent::class => 'beforeWrite'],
            CustomerVatIdCountrySubscriber::getSubscribedEvents()
        );
    }

    public function testResolvesTheMemberStateOfTheFirstVatId(): void
    {
        $countryId = Uuid::randomHex();

        $provider = $this->createMock(VatIdPatternProvider::class);
        $provider->expects($this->once())
            ->method('getCountryIdForVatIds')
            ->with(['NL123456789B01', 'DE123456789'])
            ->willReturn($countryId);

        $command = $this->createMock(WriteCommand::class);
        $command->method('hasField')->with('vat_ids')->willReturn(true);
        $command->method('getPayload')->willReturn(['vat_ids' => '["NL123456789B01","DE123456789"]']);
        $command->expects($this->once())
            ->method('addPayload')
            ->with('vat_id_country_id', Uuid::fromHexToBytes($countryId));

        $this->dispatch($command, $provider);
    }

    public function testStoresNullWhenNoMemberStateMatches(): void
    {
        $provider = $this->createStub(VatIdPatternProvider::class);
        $provider->method('getCountryIdForVatIds')->willReturn(null);

        $command = $this->createMock(WriteCommand::class);
        $command->method('hasField')->with('vat_ids')->willReturn(true);
        $command->method('getPayload')->willReturn(['vat_ids' => '["not-a-vat-id"]']);
        $command->expects($this->once())
            ->method('addPayload')
            ->with('vat_id_country_id', null);

        $this->dispatch($command, $provider);
    }

    public function testClearsTheCountryWhenTheVatIdsAreRemoved(): void
    {
        $provider = $this->createMock(VatIdPatternProvider::class);
        $provider->expects($this->once())
            ->method('getCountryIdForVatIds')
            ->with(null)
            ->willReturn(null);

        $command = $this->createMock(WriteCommand::class);
        $command->method('hasField')->with('vat_ids')->willReturn(true);
        $command->method('getPayload')->willReturn(['vat_ids' => null]);
        $command->expects($this->once())
            ->method('addPayload')
            ->with('vat_id_country_id', null);

        $this->dispatch($command, $provider);
    }

    public function testIgnoresWritesThatDoNotTouchTheVatIds(): void
    {
        $provider = $this->createMock(VatIdPatternProvider::class);
        $provider->expects($this->never())->method('getCountryIdForVatIds');

        $command = $this->createMock(WriteCommand::class);
        $command->method('hasField')->with('vat_ids')->willReturn(false);
        $command->expects($this->never())->method('addPayload');

        $this->dispatch($command, $provider);
    }

    private function dispatch(WriteCommand $command, VatIdPatternProvider $provider): void
    {
        $event = $this->createMock(EntityWriteEvent::class);
        $event->expects($this->once())
            ->method('getCommandsForEntity')
            ->with(CustomerDefinition::ENTITY_NAME)
            ->willReturn([$command]);

        (new CustomerVatIdCountrySubscriber($provider))->beforeWrite($event);
    }
}
