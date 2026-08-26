<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Newsletter\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Newsletter\ScheduledTask\NewsletterRecipientTaskHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('after-sales')]
class NewsletterRecipientTaskHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testRunSearchesForExpiredNewsletterRecipients(): void
    {
        $clock = new MockClock();
        $capturedCriteria = null;

        $recipientRepository = StaticEntityRepository::of(NewsletterRecipientCollection::class, [
            static function (Criteria $criteria) use (&$capturedCriteria): array {
                $capturedCriteria = $criteria;

                return [];
            },
        ]);

        $taskHandler = new NewsletterRecipientTaskHandler(
            static::getContainer()->get('scheduled_task.repository'),
            static::createStub(LoggerInterface::class),
            $recipientRepository,
            $clock
        );

        $taskHandler->run();

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        $expiredBefore = $clock->now()->modify('-30 days')->format(\DATE_ATOM);

        $filters = $capturedCriteria->getFilters();

        $orFilter = array_shift($filters);
        static::assertInstanceOf(OrFilter::class, $orFilter);

        $orFilters = $orFilter->getQueries();

        $notSetRecipientFilter = array_shift($orFilters);
        static::assertInstanceOf(AndFilter::class, $notSetRecipientFilter);

        $notSetRecipientFilters = $notSetRecipientFilter->getQueries();

        $notSetRecipientStatusFilter = array_shift($notSetRecipientFilters);
        static::assertInstanceOf(EqualsFilter::class, $notSetRecipientStatusFilter);
        static::assertSame('status', $notSetRecipientStatusFilter->getField());
        static::assertSame('notSet', $notSetRecipientStatusFilter->getValue());

        $notSetRecipientCreatedAtFilter = array_shift($notSetRecipientFilters);
        static::assertInstanceOf(RangeFilter::class, $notSetRecipientCreatedAtFilter);
        static::assertSame('createdAt', $notSetRecipientCreatedAtFilter->getField());
        static::assertSame($expiredBefore, $notSetRecipientCreatedAtFilter->getParameter(RangeFilter::LTE));

        $optOutRecipientFilter = array_shift($orFilters);
        static::assertInstanceOf(AndFilter::class, $optOutRecipientFilter);

        $optOutRecipientFilters = $optOutRecipientFilter->getQueries();

        $optOutRecipientStatusFilter = array_shift($optOutRecipientFilters);
        static::assertInstanceOf(EqualsFilter::class, $optOutRecipientStatusFilter);
        static::assertSame('status', $optOutRecipientStatusFilter->getField());
        static::assertSame('optOut', $optOutRecipientStatusFilter->getValue());

        $optOutRecipientUpdatedAtFilter = array_shift($optOutRecipientFilters);
        static::assertInstanceOf(RangeFilter::class, $optOutRecipientUpdatedAtFilter);
        static::assertSame('updatedAt', $optOutRecipientUpdatedAtFilter->getField());
        static::assertSame($expiredBefore, $optOutRecipientUpdatedAtFilter->getParameter(RangeFilter::LTE));

        static::assertSame(999, $capturedCriteria->getLimit());
    }

    public function testRun(): void
    {
        // pin the handler clock to the fixture timestamp: the 30-day fixture sits on the
        // handler's LTE cutoff and only survives through its sub-second precision, so with
        // a real clock the outcome flips whenever fixture install and task run straddle a
        // second boundary
        $now = new \DateTimeImmutable('2026-01-15 10:00:00.500');
        $this->installTestData($now);

        $taskHandler = $this->getTaskHandler(new MockClock($now));
        $taskHandler->run();

        /** @var EntityRepository<NewsletterRecipientCollection> */
        $repository = static::getContainer()->get('newsletter_recipient.repository');
        $result = $repository->searchIds(new Criteria(), Context::createDefaultContext());

        $expectedIds = [
            'b4b45f58088d41289490db956ca19af7',
            '7912f4de72aa43d792bcebae4eb45c5c',
            'ee367309f56445bf88ab944c81907951',
            '0d095dffd93b48a6b22300a1dad879d4',
            '0d095dffd93b48a6b22300a1dad879d5',
        ];

        static::assertCount(\count($expectedIds), $result->getData());

        foreach ($expectedIds as $id) {
            static::assertContains($id, array_keys($result->getData()), print_r(array_keys($result->getData()), true));
        }

        $deletedIds = [
            '9420908cc96b42379ff86fa1e5a6f10b',
            '0d095dffd93b48a6b22300a1dad879d3',
            '0d095dffd93b48a6b22300a1dad879d6',
        ];

        foreach ($deletedIds as $id) {
            static::assertNotContains($id, array_keys($result->getData()), print_r(array_keys($result->getData()), true));
        }
    }

    private function installTestData(\DateTimeImmutable $now): void
    {
        $salutationSql = file_get_contents(__DIR__ . '/../fixtures/salutation.sql');
        static::assertIsString($salutationSql);
        static::getContainer()->get(Connection::class)->executeStatement($salutationSql);

        $recipientSql = file_get_contents(__DIR__ . '/../fixtures/recipient.sql');
        static::assertIsString($recipientSql);
        $recipientSql = str_replace(':now', $now->format(Defaults::STORAGE_DATE_TIME_FORMAT), $recipientSql);
        static::getContainer()->get(Connection::class)->executeStatement($recipientSql);
    }

    private function getTaskHandler(ClockInterface $clock): NewsletterRecipientTaskHandler
    {
        return new NewsletterRecipientTaskHandler(
            static::getContainer()->get('scheduled_task.repository'),
            $this->createMock(LoggerInterface::class),
            static::getContainer()->get('newsletter_recipient.repository'),
            $clock
        );
    }
}
