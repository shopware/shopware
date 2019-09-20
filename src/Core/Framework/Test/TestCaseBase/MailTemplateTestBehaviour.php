<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

trait MailTemplateTestBehaviour
{
    private function assignMailtemplatesToSalesChannel(string $salesChannelId, Context $context): void
    {
        $mailTemplateRepository = $this->getContainer()->get('mail_template.repository');
        $mailTemplates = $mailTemplateRepository->search(new Criteria(), $context);

        $mailTemplatesArray = [];

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
