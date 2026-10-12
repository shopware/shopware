<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Newsletter\ScheduledTask\NewsletterRecipientTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(NewsletterRecipientTaskHandler::class)]
class NewsletterRecipientTaskHandlerTest extends TestCase
{
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

        $this->createHandler($recipientRepository, $clock)->run();

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

    public function testRunDeletesMatchingRecipientIds(): void
    {
        $recipientRepository = StaticEntityRepository::of(NewsletterRecipientCollection::class, [['recipient-id']]);

        $this->createHandler($recipientRepository, new MockClock())->run();

        static::assertSame([[['id' => 'recipient-id']]], $recipientRepository->deletes);
    }

    public function testRunDoesNotDeleteWhenNoRecipientsMatch(): void
    {
        $recipientRepository = StaticEntityRepository::of(NewsletterRecipientCollection::class, [[]]);

        $this->createHandler($recipientRepository, new MockClock())->run();

        static::assertSame([], $recipientRepository->deletes);
    }

    /**
     * @param EntityRepository<NewsletterRecipientCollection> $recipientRepository
     */
    private function createHandler(EntityRepository $recipientRepository, ClockInterface $clock): NewsletterRecipientTaskHandler
    {
        return new NewsletterRecipientTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $recipientRepository,
            $clock,
        );
    }
}
