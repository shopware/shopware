<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerLanguageSalesChannelSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Test\Assert\WriteViolations;
use Shopware\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerLanguageSalesChannelSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->createSalesChannel(['id' => $this->ids->get('english'), 'domains' => [['url' => 'http://english.test']]]);
    }

    public function testACustomerMayUseALanguageOfTheirSalesChannel(): void
    {
        WriteViolations::assertAccepted(fn () => $this->createCustomerWithLanguage(Defaults::LANGUAGE_SYSTEM, Context::createDefaultContext()));
    }

    public function testACustomerCannotUseALanguageTheirSalesChannelDoesNotHave(): void
    {
        WriteViolations::assertRejected(
            [CustomerLanguageSalesChannelSubscriber::VIOLATION_LANGUAGE_NOT_IN_SALES_CHANNEL],
            fn () => $this->createCustomerWithLanguage($this->getDeDeLanguageId(), Context::createDefaultContext()),
        );
    }

    public function testAChangedLanguageIsCheckedAgainstTheStoredSalesChannel(): void
    {
        $this->createCustomerWithLanguage(Defaults::LANGUAGE_SYSTEM, Context::createDefaultContext());

        WriteViolations::assertRejected(
            [CustomerLanguageSalesChannelSubscriber::VIOLATION_LANGUAGE_NOT_IN_SALES_CHANNEL],
            fn () => $this->updateCustomer(['languageId' => $this->getDeDeLanguageId()]),
        );
    }

    public function testACustomerCannotMoveToASalesChannelWithoutTheirLanguage(): void
    {
        $this->createCustomerWithLanguage(Defaults::LANGUAGE_SYSTEM, Context::createDefaultContext());
        $this->createGermanSalesChannel();

        WriteViolations::assertRejected(
            [CustomerLanguageSalesChannelSubscriber::VIOLATION_LANGUAGE_NOT_IN_SALES_CHANNEL],
            fn () => $this->updateCustomer(['salesChannelId' => $this->ids->get('german')]),
        );
    }

    public function testWritesThroughTheStoreApiAreNotChecked(): void
    {
        WriteViolations::assertAccepted(
            fn () => $this->createCustomerWithLanguage($this->getDeDeLanguageId(), new Context(new SalesChannelApiSource($this->ids->get('english')))),
        );
    }

    private function createGermanSalesChannel(): void
    {
        $germanId = $this->getDeDeLanguageId();

        $this->createSalesChannel([
            'id' => $this->ids->get('german'),
            'languageId' => $germanId,
            'languages' => [['id' => $germanId]],
            'domains' => [['languageId' => $germanId, 'url' => 'http://german.test']],
        ]);
    }

    private function createCustomerWithLanguage(string $languageId, Context $context): void
    {
        $customer = (new CustomerBuilder($this->ids, 'customer', $this->ids->get('english')))
            ->add('languageId', $languageId)
            ->build();

        static::getContainer()->get('customer.repository')->create([$customer], $context);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function updateCustomer(array $values): void
    {
        static::getContainer()->get('customer.repository')->update([['id' => $this->ids->get('customer'), ...$values]], Context::createDefaultContext());
    }
}
