<?php

declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Event\ContextCreatedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 */
#[Package('framework')]
class ContextFactory
{
    /**
     * @internal
     */
    public function __construct(
        private Connection $connection,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param array{originalContext?: Context, version-id?: string, languageId?: string} $options
     */
    public function getContext(string $salesChannelId, array $options): Context
    {
        $sql = '
        # context-factory::base-context

        SELECT
          sales_channel.id as sales_channel_id,
          sales_channel.language_id as sales_channel_default_language_id,
          sales_channel.currency_id as sales_channel_currency_id,
          currency.factor as sales_channel_currency_factor,
          GROUP_CONCAT(LOWER(HEX(sales_channel_language.language_id))) as sales_channel_language_ids
        FROM sales_channel
            INNER JOIN currency
                ON sales_channel.currency_id = currency.id
            LEFT JOIN sales_channel_language
                ON sales_channel_language.sales_channel_id = sales_channel.id
        WHERE sales_channel.id = :id
        GROUP BY sales_channel.id, sales_channel.language_id, sales_channel.currency_id, currency.factor';

        $data = $this->connection->fetchAssociative($sql, [
            'id' => Uuid::fromHexToBytes($salesChannelId),
        ]);
        if ($data === false) {
            throw SalesChannelException::noContextData($salesChannelId);
        }

        if (isset($options[SalesChannelContextService::ORIGINAL_CONTEXT])) {
            $origin = new AdminSalesChannelApiSource($salesChannelId, $options[SalesChannelContextService::ORIGINAL_CONTEXT]);
        } else {
            $origin = new SalesChannelApiSource($salesChannelId);
        }

        // explode all available languages for the provided sales channel
        $languageIds = $data['sales_channel_language_ids'] ? explode(',', (string) $data['sales_channel_language_ids']) : [];
        $languageIds = array_keys(array_flip($languageIds));

        // check which language should be used in the current request (request header set, or context already contains a language - stored in `sales_channel_api_context`)
        $defaultLanguageId = Uuid::fromBytesToHex($data['sales_channel_default_language_id']);

        $languageChain = $this->buildLanguageChain($options, $defaultLanguageId, $languageIds);

        $versionId = $options[SalesChannelContextService::VERSION_ID] ?? Defaults::LIVE_VERSION;

        return $this->eventDispatcher->dispatch(new ContextCreatedEvent(
            new Context(
                $origin,
                [],
                Uuid::fromBytesToHex($data['sales_channel_currency_id']),
                $languageChain,
                $versionId,
                (float) $data['sales_channel_currency_factor'],
                true
            ),
        ))->context;
    }

    /**
     * @param array{originalContext?: Context, version-id?: string, languageId?: string} $sessionOptions
     * @param array<string> $availableLanguageIds
     *
     * @return non-empty-list<string>
     */
    private function buildLanguageChain(array $sessionOptions, string $defaultLanguageId, array $availableLanguageIds): array
    {
        $current = $sessionOptions[SalesChannelContextService::LANGUAGE_ID] ?? $defaultLanguageId;

        if (!\is_string($current) || !Uuid::isValid($current)) {
            throw SalesChannelException::invalidLanguageId();
        }

        // check provided language is part of the available languages
        if (!\in_array($current, $availableLanguageIds, true)) {
            throw SalesChannelException::providedLanguageNotAvailable($current, $availableLanguageIds);
        }

        if ($current === Defaults::LANGUAGE_SYSTEM) {
            return [Defaults::LANGUAGE_SYSTEM];
        }

        // provided language can be a child language
        return array_values(array_filter([$current, $this->getParentLanguageId($current), Defaults::LANGUAGE_SYSTEM]));
    }

    private function getParentLanguageId(string $languageId): ?string
    {
        $data = $this->connection->createQueryBuilder()
            ->select('LOWER(HEX(language.parent_id))')
            ->from('language')
            ->where('language.id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($languageId))
            ->executeQuery()
            ->fetchOne();

        if ($data === false) {
            throw SalesChannelException::languageNotFound($languageId);
        }

        return $data;
    }
}
