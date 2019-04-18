<?php declare(strict_types=1);

namespace Shopware\Core\Content\NewsletterReceiver\ScheduledTask;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\ScheduledTask\ScheduledTaskHandler;

class NewsletterReceiverTaskHandler extends ScheduledTaskHandler
{
    /**
     * @var EntityRepositoryInterface
     */
    private $newsletterReceiverRepository;

    public function __construct(EntityRepositoryInterface $scheduledTaskRepository, EntityRepositoryInterface $newsletterReceiverRepository)
    {
        parent::__construct($scheduledTaskRepository);

        $this->newsletterReceiverRepository = $newsletterReceiverRepository;
    }

    public static function getHandledMessages(): iterable
    {
        return [
            NewsletterReceiverTask::class,
        ];
    }

    public function run(): void
    {
        $criteria = $this->getExpiredNewsletterReceiverCriteria();
        $emailReceiver = $this->newsletterReceiverRepository->searchIds($criteria, Context::createDefaultContext());

        if (empty($emailReceiver->getIds())) {
            return;
        }

        $emailReceiverIds = array_map(function ($id) {return ['id' => $id]; }, $emailReceiver->getIds());

        $this->newsletterReceiverRepository->delete($emailReceiverIds, Context::createDefaultContext());
    }

    private function getExpiredNewsletterReceiverCriteria(): Criteria
    {
        $criteria = new Criteria();

        $dateTime = (new \DateTime())->add(\DateInterval::createFromDateString('-30 days'));

        $criteria->addFilter(new RangeFilter(
            'createdAt',
            [
                RangeFilter::LTE => $dateTime->format(DATE_ATOM),
            ]
        ));

        $criteria->addFilter(new EqualsFilter('status', 'notSet'));

        $criteria->setLimit(999);

        return $criteria;
    }
}
