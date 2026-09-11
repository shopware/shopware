<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Event;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * @internal
 *
 * The To: header name is built in the events, not in the mail template, so a nameless commercial
 * account would arrive as a bare space unless every one of them resolves the name.
 */
#[Package('checkout')]
trait MailRecipientNameTestBehaviour
{
    /**
     * @param \Closure(CustomerEntity, SalesChannelContext): MailAware $build
     */
    private function assertRecipientNames(\Closure $build): void
    {
        $company = $this->mailCustomer('', '');
        $company->setDisplayName('Acme GmbH');

        static::assertSame(
            ['info@acme.example' => 'Acme GmbH'],
            $build($company, $this->mailSalesChannelContext())->getMailStruct()->getRecipients(),
            'a company account without a contact person is addressed by its company'
        );

        $person = $this->mailCustomer('Ada', 'Lovelace');
        $person->setDisplayName('Ada Lovelace');

        static::assertSame(
            ['info@acme.example' => 'Ada Lovelace'],
            $build($person, $this->mailSalesChannelContext())->getMailStruct()->getRecipients(),
            'a contact person is still addressed by name'
        );

        static::assertSame(
            ['info@acme.example' => 'Ada Lovelace'],
            $build($this->mailCustomer('Ada', 'Lovelace'), $this->mailSalesChannelContext())->getMailStruct()->getRecipients(),
            'the display name is a runtime field, so an unresolved customer falls back to the person name'
        );
    }

    private function mailCustomer(string $firstName, string $lastName): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId('customer-id');
        $customer->setUniqueIdentifier('customer-id');
        $customer->setEmail('info@acme.example');
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_BUSINESS);
        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);
        $customer->setCompany('Acme GmbH');

        return $customer;
    }

    private function mailSalesChannelContext(): SalesChannelContext
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        // Two of the events read the shop name out of the sales channel translation on construction.
        $salesChannelContext->getSalesChannel()->setTranslated(['name' => 'Demostore']);

        return $salesChannelContext;
    }
}
