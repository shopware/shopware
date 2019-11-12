<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

trait MailTemplateTestBehaviour
{
    private function assignMailtemplatesToSalesChannel(string $salesChannelId, Context $context): void
    {
        $mailTemplateRepository = $this->getContainer()->get('mail_template.repository');
        $mailTemplates = $mailTemplateRepository->search(new Criteria(), $context);

        $mailTemplatesArray = [];

        $this->getContainer()
            ->get(Connection::class)
            ->executeUpdate('DELETE FROM mail_template_sales_channel WHERE sales_channel_id = :id', [
                'id' => Uuid::fromHexToBytes($salesChannelId),
            ]);

        /** @var MailTemplateEntity $mailTemplate */
        foreach ($mailTemplates as $mailTemplate) {
            $mailTemplatesArray[] = [
                'id' => $mailTemplate->getId(),
                'salesChannels' => [
                    [
                        'mailTemplateTypeId' => $mailTemplate->getMailTemplateTypeId(),
                        'salesChannelId' => $salesChannelId,
                    ],
                ],
            ];
        }

        $mailTemplateRepository->update(
            $mailTemplatesArray,
            $context
        );
    }
}
