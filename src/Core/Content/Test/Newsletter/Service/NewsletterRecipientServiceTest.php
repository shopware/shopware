<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Newsletter\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Exception\NewsletterRecipientNotFoundException;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterConfirmRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterUnsubscribeRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('customer-order')]
class NewsletterRecipientServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider dataProvider_testSubscribeNewsletterExpectsConstraintViolationException
     */
    public function testSubscribeNewsletterExpectsConstraintViolationException(array $testData): void
    {
        $this->installTestData();
        $dataBag = new RequestDataBag($testData);

        self::expectException(ConstraintViolationException::class);

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $this->getContainer()->get(NewsletterSubscribeRoute::class)
            ->subscribe($dataBag, $context, false);
    }

    public static function dataProvider_testSubscribeNewsletterExpectsConstraintViolationException(): array
    {
        $testData1 = ['email' => null, 'salutationId' => null, 'option' => null];
        $testData2 = ['email' => '', 'salutationId' => null, 'option' => null];
        $testData3 = ['email' => '', 'salutationId' => '', 'option' => null];
        $testData4 = ['email' => '', 'salutationId' => '', 'option' => null];
        $testData5 = ['email' => '', 'salutationId' => '', 'option' => ''];

        // test Not Valid Email
        $testDataEmail1 = ['email' => '', 'salutationId' => 'ad165c1faac14059832b6258ac0a7339', 'option' => 'subscribe'];
        $testDataEmail2 = ['email' => 'notValid', 'salutationId' => 'ad165c1faac14059832b6258ac0a7339', 'option' => 'subscribe'];
        $testDataEmail3 = ['email' => 'notValid@', 'salutationId' => 'ad165c1faac14059832b6258ac0a7339', 'option' => 'subscribe'];
        $testDataEmail4 = ['email' => 'notValid@foo', 'salutationId' => 'ad165c1faac14059832b6258ac0a7339', 'option' => 'subscribe'];
        $testDataEmail5 = ['email' => 'notValid@foo.', 'salutationId' => 'ad165c1faac14059832b6258ac0a7339', 'option' => 'subscribe'];

        // test not valid option
        $testDataOption1 = ['option' => '', 'email' => 'valid@email.foo', 'salutationId' => 'ad165c1faac14059832b6258ac0a7339'];
        $testDataOption2 = ['option' => 'notValid', 'email' => 'valid@email.foo', 'salutationId' => 'ad165c1faac14059832b6258ac0a7339'];
        $testDataOption3 = ['option' => 'unitTest', 'email' => 'valid@email.foo', 'salutationId' => 'ad165c1faac14059832b6258ac0a7339'];
        $testDataOption4 = ['option' => 'otherValue', 'email' => 'valid@email.foo', 'salutationId' => 'ad165c1faac14059832b6258ac0a7339'];

        return [
            [$testData1],
            [$testData2],
            [$testData3],
            [$testData4],
            [$testData5],
            [$testDataEmail1],
            [$testDataEmail2],
            [$testDataEmail3],
            [$testDataEmail4],
            [$testDataEmail5],
            [$testDataOption1],
            [$testDataOption2],
            [$testDataOption3],
            [$testDataOption4],
        ];
    }

    public function testSubscribeNewsletterShouldSaveRecipientToDatabase(): void
    {
        $this->installTestData();
        $email = 'valid@email.foo';
        $dataBag = new RequestDataBag([
            'storefrontUrl' => '',
            'email' => $email,
            'salutationId' => 'ad165c1faac14059832b6258ac0a7339',
            'baseUrl' => '',
            'option' => 'subscribe',
            'firstName' => 'max',
            'lastName' => 'mustermann',
        ]);

        $id = Uuid::randomHex();
        $salesChannel = [
            'id' => $id,
            'name' => 'test',
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $this->getRandomId('payment_method'),
            'shippingMethodId' => $this->getRandomId('shipping_method'),
            'countryId' => $this->getRandomId('country'),
            'navigationCategoryId' => $this->getRandomId('category'),
            'accessKey' => 'test',
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
            ],
            'domains' => [
                [
                    'url' => 'http://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ];

        $this->getContainer()->get('sales_channel.repository')
            ->create([$salesChannel], Context::createDefaultContext());

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), $id);
        $this->getContainer()
            ->get(NewsletterSubscribeRoute::class)
            ->subscribe($dataBag, $context, false);

        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('newsletter_recipient.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var NewsletterRecipientEntity $result */
        $result = $repository->search($criteria, $context->getContext())->getEntities()->first();

        static::assertInstanceOf(NewsletterRecipientEntity::class, $result);
        static::assertSame($email, $result->getEmail());
        static::assertSame('notSet', $result->getStatus());
    }

    public function testConfirmSubscribeNewsletterExpectsNewsletterRecipientNotFoundException(): void
    {
        $dataBag = new RequestDataBag(['hash' => 'notExistentHash']);

        self::expectException(NewsletterRecipientNotFoundException::class);

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $this->getContainer()
            ->get(NewsletterConfirmRoute::class)
            ->confirm($dataBag, $context);
    }

    public function testConfirmSubscribeNewsletterExpectsConstraintViolationException(): void
    {
        $this->installTestData();

        $dataBag = new RequestDataBag(['em' => 'notValidHash', 'hash' => 'b4b45f58088d41289490db956ca19af7']);

        self::expectException(ConstraintViolationException::class);

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $this->getContainer()
            ->get(NewsletterConfirmRoute::class)
            ->confirm($dataBag, $context);
    }

    public function testConfirmSubscribeNewsletterExpectedUpdatedDatabaseRow(): void
    {
        $this->installTestData();

        $email = 'unit@test.foo';
        $dataBag = new RequestDataBag([
            'em' => hash('sha1', $email),
            'hash' => 'b4b45f58088d41289490db956ca19af7',
        ]);

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $this->getContainer()
            ->get(NewsletterConfirmRoute::class)
            ->confirm($dataBag, $context);

        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('newsletter_recipient.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var NewsletterRecipientEntity $result */
        $result = $repository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(NewsletterRecipientEntity::class, $result);
        static::assertNotNull($result->getConfirmedAt());
        static::assertSame((new \DateTime())->format('y-m-d'), $result->getConfirmedAt()->format('y-m-d'));
        static::assertSame('optIn', $result->getStatus());
    }

    public function testUnsubscribeNewsletterExpectsNewsletterRecipientNotFoundException(): void
    {
        $this->installTestData();
        $email = 'not@existend.email';
        $dataBag = new RequestDataBag([
            'email' => $email,
            'salutationId' => 'ad165c1faac14059832b6258ac0a7339',
            'option' => 'unsubscribe',
        ]);

        self::expectException(NewsletterRecipientNotFoundException::class);

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $this->getContainer()
            ->get(NewsletterUnsubscribeRoute::class)
            ->unsubscribe($dataBag, $context);
    }

    public function testConfirmSubscribeNewsletterExpectsUpdatedDatabaseRow(): void
    {
        $this->installTestData();

        $email = 'unit@test.foo';
        $dataBag = new RequestDataBag([
            'email' => $email,
            'salutationId' => 'AD165C1FAAC14059832B6258AC0A7339',
            'option' => 'unsubscribe',
        ]);

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $this->getContainer()
            ->get(NewsletterUnsubscribeRoute::class)
            ->unsubscribe($dataBag, $context);

        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('newsletter_recipient.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var NewsletterRecipientEntity $result */
        $result = $repository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(NewsletterRecipientEntity::class, $result);
        static::assertSame($email, $result->getEmail());
        static::assertNotNull($result->getUpdatedAt());
        static::assertSame((new \DateTime())->format('y-m-d'), $result->getUpdatedAt()->format('y-m-d'));
    }

    private function getRandomId(string $table)
    {
        return $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT LOWER(HEX(id)) FROM ' . $table);
    }

    private function installTestData(): void
    {
        $salutationSql = file_get_contents(__DIR__ . '/../fixtures/salutation.sql');
        static::assertIsString($salutationSql);
        $this->getContainer()->get(Connection::class)->executeStatement($salutationSql);

        $recipientSql = file_get_contents(__DIR__ . '/../fixtures/recipient.sql');
        static::assertIsString($recipientSql);
        $recipientSql = str_replace(':createdAt', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), $recipientSql);
        $this->getContainer()->get(Connection::class)->executeStatement($recipientSql);
    }
}
