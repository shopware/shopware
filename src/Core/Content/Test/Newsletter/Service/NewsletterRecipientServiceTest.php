<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Newsletter\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Exception\NewsletterRecipientNotFoundException;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscriptionService;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscriptionServiceInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;

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
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $this->getService()->subscribe($dataBag, $context);
    }

    public function dataProvider_testSubscribeNewsletterExpectsConstraintViolationException(): array
    {
        $testData1 = ['email' => null, 'salutationId' => null, 'option' => null];
        $testData2 = ['email' => '', 'salutationId' => null, 'option' => null];
        $testData3 = ['email' => '', 'salutationId' => '', 'option' => null];
        $testData4 = ['email' => '', 'salutationId' => '', 'option' => null];
        $testData5 = ['email' => '', 'salutationId' => '', 'option' => ''];

        // test Not Valid Email
        $testDataEmail1 = ['email' => '', 'salutationId' => 'AD165C1FAAC14059832B6258AC0A7339', 'option' => 'subscribe'];
        $testDataEmail2 = ['email' => 'notValid', 'salutationId' => 'AD165C1FAAC14059832B6258AC0A7339', 'option' => 'subscribe'];
        $testDataEmail3 = ['email' => 'notValid@', 'salutationId' => 'AD165C1FAAC14059832B6258AC0A7339', 'option' => 'subscribe'];
        $testDataEmail4 = ['email' => 'notValid@foo', 'salutationId' => 'AD165C1FAAC14059832B6258AC0A7339', 'option' => 'subscribe'];
        $testDataEmail5 = ['email' => 'notValid@foo.', 'salutationId' => 'AD165C1FAAC14059832B6258AC0A7339', 'option' => 'subscribe'];

        // test not valid option
        $testDataOption1 = ['option' => '', 'email' => 'valid@email.foo', 'salutationId' => 'AD165C1FAAC14059832B6258AC0A7339'];
        $testDataOption2 = ['option' => 'notValid', 'email' => 'valid@email.foo', 'salutationId' => 'AD165C1FAAC14059832B6258AC0A7339'];
        $testDataOption3 = ['option' => 'unitTest', 'email' => 'valid@email.foo', 'salutationId' => 'AD165C1FAAC14059832B6258AC0A7339'];
        $testDataOption4 = ['option' => 'otherValue', 'email' => 'valid@email.foo', 'salutationId' => 'AD165C1FAAC14059832B6258AC0A7339'];

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
            'email' => $email,
            'salutationId' => 'AD165C1FAAC14059832B6258AC0A7339',
            'baseUrl' => '',
            'option' => 'subscribe',
            'firstName' => '',
            'lastName' => '',
        ]);

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $this->getService()->subscribe($dataBag, $context);

        /** @var EntityRepositoryInterface $repository */
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
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $this->getService()->confirm($dataBag, $context);
    }

    public function testConfirmSubscribeNewsletterExpectsConstraintViolationException(): void
    {
        $this->installTestData();

        $dataBag = new RequestDataBag(['em' => 'notValidHash', 'hash' => 'b4b45f58088d41289490db956ca19af7']);

        self::expectException(ConstraintViolationException::class);

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $this->getService()->confirm($dataBag, $context);
    }

    public function testConfirmSubscribeNewsletterExpectedUpdatedDatabaseRow(): void
    {
        $this->installTestData();

        $email = 'unit@test.foo';
        $dataBag = new RequestDataBag([
            'em' => hash('sha1', $email),
            'hash' => 'b4b45f58088d41289490db956ca19af7',
        ]);

        $languageId = Uuid::fromBytesToHex(
            $this->getContainer()->get(Connection::class)->fetchColumn('SELECT `id` FROM `language` LIMIT 1')
        );

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $property = ReflectionHelper::getProperty(Context::class, 'languageIdChain');
        $property->setValue($context, [$languageId]);

        $this->getService()->confirm($dataBag, $context);

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('newsletter_recipient.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var NewsletterRecipientEntity $result */
        $result = $repository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(NewsletterRecipientEntity::class, $result);
        static::assertNotNull($result->getConfirmedAt());
        static::assertSame((new \DateTime())->format('y-m-d'), $result->getConfirmedAt()->format('y-m-d'));
        static::assertSame(NewsletterSubscriptionServiceInterface::STATUS_OPT_IN, $result->getStatus());
    }

    public function testUnsubscribeNewsletterExpectsNewsletterRecipientNotFoundException(): void
    {
        $this->installTestData();
        $email = 'not@existend.email';
        $dataBag = new RequestDataBag([
            'email' => $email,
            'salutationId' => 'AD165C1FAAC14059832B6258AC0A7339',
            'option' => 'unsubscribe',
        ]);

        self::expectException(NewsletterRecipientNotFoundException::class);

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $this->getService()->unsubscribe($dataBag, $context);
    }

    /**
     * @dataProvider dataProviderTestUnsubscribeNewsletterExpectsConstraintViolationException
     */
    public function testUnsubscribeNewsletterExpectsConstraintViolationException(array $testData): void
    {
        $this->installTestData();
        $dataBag = new RequestDataBag($testData);

        self::expectException(ConstraintViolationException::class);

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $this->getService()->unsubscribe($dataBag, $context);
    }

    public function dataProviderTestUnsubscribeNewsletterExpectsConstraintViolationException(): array
    {
        // Option is not valid
        return [
            [['option' => 'direct', 'email' => 'unit@test.foo', 'salutationId' => 'AD165C1FAAC14059832B6258AC0A7339']],
            [['option' => 'subscribe', 'email' => 'unit@test.foo', 'salutationId' => 'AD165C1FAAC14059832B6258AC0A7339']],
            [['option' => 'confirmSubscribe', 'email' => 'unit@test.foo', 'salutationId' => 'AD165C1FAAC14059832B6258AC0A7339']],
        ];
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
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $this->getService()->unsubscribe($dataBag, $context);

        /** @var EntityRepositoryInterface $repository */
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

    private function installTestData(): void
    {
        $salutationSql = file_get_contents(__DIR__ . '/../fixtures/salutation.sql');
        $this->getContainer()->get(Connection::class)->exec($salutationSql);

        $recipientSql = file_get_contents(__DIR__ . '/../fixtures/recipient.sql');
        $this->getContainer()->get(Connection::class)->exec($recipientSql);

        $templateSql = file_get_contents(__DIR__ . '/../fixtures/template.sql');
        $this->getContainer()->get(Connection::class)->exec($templateSql);
    }

    private function getService(): NewsletterSubscriptionService
    {
        return new NewsletterSubscriptionService(
            $this->getContainer()->get('newsletter_recipient.repository'),
            $this->getContainer()->get(DataValidator::class),
            $this->getContainer()->get('event_dispatcher')
        );
    }
}
