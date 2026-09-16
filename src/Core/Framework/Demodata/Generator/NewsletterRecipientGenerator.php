<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataException;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Demodata\DemodataService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
class NewsletterRecipientGenerator implements DemodataGeneratorInterface
{
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly NewsletterRecipientDefinition $newsletterRecipientDefinition,
        private readonly Connection $connection,
    ) {
    }

    public function getDefinition(): string
    {
        return NewsletterRecipientDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        $salesChannelIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM sales_channel');
        if ($salesChannelIds === []) {
            throw DemodataException::wrongExecutionOrder();
        }

        $writeContext = WriteContext::createFromContext($context->getContext());
        $faker = $context->getFaker();

        $payload = [];
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $id = Uuid::randomHex();

            $newsletterRecipient = [
                'id' => $id,
                'email' => $id . $faker->format('safeEmail'),
                'firstName' => $faker->format('firstName'),
                'lastName' => $faker->format('lastName'),
                'status' => NewsletterSubscribeRoute::STATUS_DIRECT,
                'hash' => Uuid::randomHex(),
                'salesChannelId' => $salesChannelIds[array_rand($salesChannelIds)],
                'customFields' => [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true],
            ];

            $payload[] = $newsletterRecipient;

            if (\count($payload) >= 100) {
                $this->writer->upsert($this->newsletterRecipientDefinition, $payload, $writeContext);

                $context->getConsole()->progressAdvance(\count($payload));

                $payload = [];
            }
        }

        if ($payload !== []) {
            $this->writer->upsert($this->newsletterRecipientDefinition, $payload, $writeContext);

            $context->getConsole()->progressAdvance(\count($payload));
        }

        $context->getConsole()->progressFinish();
    }
}
