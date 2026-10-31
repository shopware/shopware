<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Event;

use PHPUnit\Framework\Assert;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
final class MailRecipientNameFixture
{
    /**
     * @param \Closure(CustomerEntity, SalesChannelContext): MailAware $build
     */
    public static function assertRecipientNames(\Closure $build): void
    {
        $company = self::customer('', '');
        $company->setDisplayName('Acme GmbH');

        Assert::assertSame(
            ['info@acme.example' => 'Acme GmbH'],
            $build($company, self::salesChannelContext())->getMailStruct()->getRecipients(),
            'a company account without a contact person is addressed by its company'
        );

        $person = self::customer('Ada', 'Lovelace');
        $person->setDisplayName('Ada Lovelace');

        Assert::assertSame(
            ['info@acme.example' => 'Ada Lovelace'],
            $build($person, self::salesChannelContext())->getMailStruct()->getRecipients(),
            'a contact person is still addressed by name'
        );
    }

    public static function customer(string $firstName, string $lastName): CustomerEntity
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

    public static function salesChannelContext(): SalesChannelContext
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setTranslated(['name' => 'Demostore']);

        return $salesChannelContext;
    }
}
