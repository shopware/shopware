<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountRegistrationService;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;

class AccountRegistrationServiceTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;
    use MailTemplateTestBehaviour;

    /**
     * @var AccountRegistrationService|null
     */
    private $accountRegistrationService;

    protected function setUp(): void
    {
        $this->accountRegistrationService = $this->getContainer()->get(AccountRegistrationService::class);
    }

    public function testRegister(): void
    {
        /** @var SalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $this->assignMailtemplatesToSalesChannel(Defaults::SALES_CHANNEL, $salesChannelContext->getContext());

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $dataBag = new DataBag();
        $dataBag->add($this->getCustomerRegisterData());

        $phpunit = $this;
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun, $phpunit, $dataBag): void {
            $eventDidRun = true;
            $phpunit->assertStringContainsString('Dear Mr. Max Mustermann', $event->getContents()['text/html']);
            $phpunit->assertStringContainsString($dataBag->get('email'), $event->getContents()['text/html']);
        };

        $eventDidRun = false;
        $dispatcher->addListener(MailSentEvent::class, $listenerClosure);

        $this->accountRegistrationService->register($dataBag, false, $salesChannelContext);

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
    }

    private function getCustomerRegisterData(): array
    {
        $personal = [
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'password' => '12345678',
            'email' => Uuid::randomHex() . '@example.com',
            'title' => 'Phd',
            'active' => true,
            'birthdayYear' => 2000,
            'birthdayMonth' => 1,
            'birthdayDay' => 22,
            'billingAddress' => new DataBag([
                'countryId' => $this->getValidCountryId(),
                'street' => 'Examplestreet 11',
                'zipcode' => '48441',
                'city' => 'Cologne',
                'phoneNumber' => '0123456789',
                'vatId' => 'DE999999999',
                'additionalAddressLine1' => 'Additional address line 1',
                'additionalAddressLine2' => 'Additional address line 2',
            ]),
            'shippingAddress' => new DataBag([
                'countryId' => $this->getValidCountryId(),
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Test 2',
                'lastName' => 'Example 2',
                'street' => 'Examplestreet 111',
                'zipcode' => '12341',
                'city' => 'Berlin',
                'phoneNumber' => '987654321',
                'additionalAddressLine1' => 'Additional address line 01',
                'additionalAddressLine2' => 'Additional address line 02',
            ]),
        ];

        return $personal;
    }
}
