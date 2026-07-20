<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Newsletter\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Newsletter\ScheduledTask\NewsletterRecipientTaskHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('after-sales')]
class NewsletterRecipientTaskHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetExpiredNewsletterRecipientCriteria(): void
    {
        $taskHandler = $this->getTaskHandler();
        $method = new \ReflectionMethod(NewsletterRecipientTaskHandler::class, 'getExpiredNewsletterRecipientCriteria');

        /** @var Criteria $criteria */
        $criteria = $method->invoke($taskHandler);

        $filters = $criteria->getFilters();
        $dateFilter = array_shift($filters);
        $equalsFilter = array_shift($filters);

        static::assertInstanceOf(RangeFilter::class, $dateFilter);
        static::assertInstanceOf(EqualsAnyFilter::class, $equalsFilter);

        static::assertSame('createdAt', $dateFilter->getField());
        static::assertNotEmpty($dateFilter->getParameter(RangeFilter::LTE));

        static::assertSame('status', $equalsFilter->getField());
        static::assertSame(['notSet', 'optOut'], $equalsFilter->getValue());
    }

    public function testRun(): void
    {
        $this->installTestData();

        $taskHandler = $this->getTaskHandler();
        $taskHandler->run();

        /** @var EntityRepository<NewsletterRecipientCollection> */
        $repository = static::getContainer()->get('newsletter_recipient.repository');
        $result = $repository->searchIds(new Criteria(), Context::createDefaultContext());

        $expectedIds = [
            'b4b45f58088d41289490db956ca19af7',
            '7912f4de72aa43d792bcebae4eb45c5c',
            'ee367309f56445bf88ab944c81907951',
            '0d095dffd93b48a6b22300a1dad879d4',
        ];

        foreach ($expectedIds as $id) {
            static::assertContains($id, array_keys($result->getData()), print_r(array_keys($result->getData()), true));
        }

        $deletedIds = [
            '9420908cc96b42379ff86fa1e5a6f10b',
            '0d095dffd93b48a6b22300a1dad879d3',
            '0d095dffd93b48a6b22300a1dad879d5',
        ];

        foreach ($deletedIds as $id) {
            static::assertNotContains($id, array_keys($result->getData()), print_r(array_keys($result->getData()), true));
        }
    }

    private function installTestData(): void
    {
        $salutationSql = file_get_contents(__DIR__ . '/../fixtures/salutation.sql');
        static::assertIsString($salutationSql);
        static::getContainer()->get(Connection::class)->executeStatement($salutationSql);

        $recipientSql = file_get_contents(__DIR__ . '/../fixtures/recipient.sql');
        static::assertIsString($recipientSql);
        $recipientSql = str_replace(':createdAt', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), $recipientSql);
        static::getContainer()->get(Connection::class)->executeStatement($recipientSql);
    }

    private function getTaskHandler(): NewsletterRecipientTaskHandler
    {
        return new NewsletterRecipientTaskHandler(
            static::getContainer()->get('scheduled_task.repository'),
            $this->createMock(LoggerInterface::class),
            static::getContainer()->get('newsletter_recipient.repository'),
            new NativeClock()
        );
    }
}
