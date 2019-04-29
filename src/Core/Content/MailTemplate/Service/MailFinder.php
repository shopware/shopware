<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;

class MailFinder
{
    /**
     * @var EntityRepositoryInterface
     */
    private $mailTemplateRepository;

    public function __construct(EntityRepositoryInterface $mailTemplateRepository)
    {
        $this->mailTemplateRepository = $mailTemplateRepository;
    }

    public function getMail(Context $context, string $salesChannelId, string $businessAction): ?MailTemplateEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter('AND', [
                /*new EqualsFilter('mail_template.salesChannels.id', $salesChannelId),*/
                new EqualsFilter('mail_template.businessActions.technicalName', $businessAction),
            ]
            )
        );

        $entity = $this->mailTemplateRepository->search($criteria, $context)->getEntities()->first();

        return $entity;
    }
}
