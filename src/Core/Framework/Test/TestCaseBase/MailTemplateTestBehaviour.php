<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

trait MailTemplateTestBehaviour
{
    public static function assertMailEvent(
        string $expectedClass,
        $event,
        SalesChannelContext $salesChannelContext
    ): void {
        TestCase::assertInstanceOf($expectedClass, $event);
        TestCase::assertSame($salesChannelContext->getContext(), $event->getContext());
    }

    public static function assertMailRecipientStructEvent(MailRecipientStruct $expectedStruct, $event): void
    {
        TestCase::assertSame($expectedStruct->getRecipients(), $event->getMailStruct()->getRecipients());
    }

    protected function catchEvent(string $eventName, &$eventResult): void
    {
        $this->getContainer()->get('event_dispatcher')->addListener($eventName, static function ($event) use (&$eventResult): void {
            $eventResult = $event;
        });
    }

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
