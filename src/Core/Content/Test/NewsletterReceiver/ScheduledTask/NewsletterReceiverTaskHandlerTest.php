<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\NewsletterReceiver\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\NewsletterReceiver\ScheduledTask\NewsletterReceiverTaskHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;

class NewsletterReceiverTaskHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetExpiredNewsletterReceiverCriteria(): void
    {
        $taskHandler = $this->getTaskHandler();
        $method = ReflectionHelper::getMethod(NewsletterReceiverTaskHandler::class, 'getExpiredNewsletterReceiverCriteria');

        /** @var Criteria $criteria */
        $criteria = $method->invoke($taskHandler);

        $filters = $criteria->getFilters();
        $dateFilter = array_shift($filters);
        $equalsFilter = array_shift($filters);

        static::assertInstanceOf(RangeFilter::class, $dateFilter);
        static::assertInstanceOf(EqualsFilter::class, $equalsFilter);

        static::assertSame('createdAt', $dateFilter->getField());
        static::assertNotEmpty($dateFilter->getParameter(RangeFilter::LTE));

        static::assertSame('status', $equalsFilter->getField());
        static::assertSame('notSet', $equalsFilter->getValue());
    }

    public function testRun()
    {
        $this->installTestData();

        $taskHandler = $this->getTaskHandler();
        $taskHandler->run();

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('newsletter_receiver.repository');
        $result = $repository->searchIds(new Criteria(), Context::createDefaultContext());

        $expectedResult = [
            '7912f4de72aa43d792bcebae4eb45c5c',
            'b4b45f58088d41289490db956ca19af7',
        ];

        static::assertSame($expectedResult, array_keys($result->getData()));
    }

    private function installTestData()
    {
        $salutationSql = file_get_contents(__DIR__ . '/../fixtures/salutation.sql');
        $this->getContainer()->get(Connection::class)->exec($salutationSql);

        $receiverSql = file_get_contents(__DIR__ . '/../fixtures/receiver.sql');
        $this->getContainer()->get(Connection::class)->exec($receiverSql);

        $templateSql = file_get_contents(__DIR__ . '/../fixtures/template.sql');
        $this->getContainer()->get(Connection::class)->exec($templateSql);
    }

    private function getTaskHandler(): NewsletterReceiverTaskHandler
    {
        return new NewsletterReceiverTaskHandler(
            $this->getContainer()->get('scheduled_task.repository'),
            $this->getContainer()->get('newsletter_receiver.repository')
        );
    }
}
