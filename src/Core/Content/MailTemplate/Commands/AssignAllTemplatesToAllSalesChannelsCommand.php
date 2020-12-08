<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Commands;

use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AssignAllTemplatesToAllSalesChannelsCommand extends Command
{
    /**
     * @var EntityRepositoryInterface
     */
    private $mailTemplateRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    public function __construct(EntityRepositoryInterface $mailTemplateRepository, EntityRepositoryInterface $salesChannelRepository)
    {
        parent::__construct();

        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->salesChannelRepository = $salesChannelRepository;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('mail-templates:assign-to-saleschannels')
            ->setDescription('Assignes all mailTemplates to all SaleChannels');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $context = Context::createDefaultContext();

        $salesChannels = $this->salesChannelRepository->search(new Criteria(), $context);

        if ($salesChannels->count() === 0) {
            $io->comment('No salesChannels found.');

            return 0;
        }
        $criteria = new Criteria();
        $criteria->addAssociation('salesChannels');
        $criteria->addAssociation('media');
        $mailtemplates = $this->mailTemplateRepository->search($criteria, $context);

        if ($mailtemplates->count() === 0) {
            $io->comment('No mailTemplates found.');

            return 0;
        }

        $confirm = $io->confirm(
            sprintf('Are you sure that you want to assign %d mailTemplates to %d salesChannels?', $mailtemplates->count(), $salesChannels->count()),
            false
        );

        if (!$confirm) {
            $io->caution('Aborting due to user input.');

            return 0;
        }

        $mailtemplatesCount = 0;
        /** @var MailTemplateEntity $mailtemplate */
        foreach ($mailtemplates as $mailtemplate) {
            $upsertData = [];
            foreach ($salesChannels as $salesChannel) {
                foreach ($mailtemplate->getSalesChannels() as $assignedSalesChannel) {
                    if ($assignedSalesChannel->getSalesChannelId() === $salesChannel->getId()) {
                        continue 2;
                    }
                }
                $upsertData['salesChannels'][] = [
                    'mailTemplateTypeId' => $mailtemplate->getMailTemplateTypeId(),
                    'salesChannelId' => $salesChannel->getId(),
                ];
            }

            if (!empty($upsertData)) {
                $upsertData['id'] = $mailtemplate->getId();
                $this->mailTemplateRepository->upsert([$upsertData], $context);
                ++$mailtemplatesCount;
            }
        }

        $io->success(sprintf('Successfully assigned %d mailTemplates to %d salesChannels.', $mailtemplatesCount, $salesChannels->count()));

        return 0;
    }
}
