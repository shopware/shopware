<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\ScheduledTask;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class NewsletterRecipientTaskHandler extends ScheduledTaskHandler
{
    /**
     * @var EntityRepositoryInterface
     */
    private $newsletterRecipientRepository;

    public function __construct(EntityRepositoryInterface $scheduledTaskRepository, EntityRepositoryInterface $newsletterRecipientRepository)
    {
        parent::__construct($scheduledTaskRepository);

        $this->newsletterRecipientRepository = $newsletterRecipientRepository;
    }

    public static function getHandledMessages(): iterable
    {
        return [
            NewsletterRecipientTask::class,
        ];
    }

    public function run(): void
    {
        $criteria = $this->getExpiredNewsletterRecipientCriteria();
        $emailRecipient = $this->newsletterRecipientRepository->searchIds($criteria, Context::createDefaultContext());

        if (empty($emailRecipient->getIds())) {
            return;
        }

        $emailRecipientIds = array_map(function ($id) {
            return ['id' => $id];
        }, $emailRecipient->getIds());

        $this->newsletterRecipientRepository->delete($emailRecipientIds, Context::createDefaultContext());
    }

    private function getExpiredNewsletterRecipientCriteria(): Criteria
    {
        $criteria = new Criteria();

        $dateTime = (new \DateTime())->add(\DateInterval::createFromDateString('-30 days'));

        $criteria->addFilter(new RangeFilter(
            'createdAt',
            [
                RangeFilter::LTE => $dateTime->format(\DATE_ATOM),
            ]
        ));

        $criteria->addFilter(new EqualsFilter('status', 'notSet'));

        $criteria->setLimit(999);

        return $criteria;
    }
}
