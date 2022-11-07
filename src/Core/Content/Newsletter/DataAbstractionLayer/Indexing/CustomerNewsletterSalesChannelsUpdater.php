<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;

class CustomerNewsletterSalesChannelsUpdater
{
    private Connection $connection;

    /**
     * @internal
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param array<string> $ids
     */
    public function update(array $ids, bool $reverseUpdate = false): void
    {
        if (empty($ids)) {
            return;
        }

        $ids = array_unique($ids);

        $tableTemplate = <<<'SQL'
UPDATE `customer`, `newsletter_recipient` SET `customer`.`newsletter_sales_channel_ids` = (
    SELECT CONCAT(
        '{',
        GROUP_CONCAT(
            CONCAT(
                JSON_QUOTE(LOWER(HEX(`newsletter_recipient`.`id`))),
                ':',
                JSON_QUOTE(LOWER(HEX(`newsletter_recipient`.`sales_channel_id`)))
            )
        ),
        '}'
    )
    FROM `newsletter_recipient`
    WHERE `newsletter_recipient`.`email` = `customer`.`email`
    AND `newsletter_recipient`.`status` IN (:states)
)
WHERE `newsletter_recipient`.`email` = `customer`.`email`
AND #table#.`id` IN (:ids)
SQL;

        $resetTemplate = <<<'SQL'
UPDATE `customer`
LEFT JOIN `newsletter_recipient` ON `newsletter_recipient`.`email` = `customer`.`email`
SET `customer`.`newsletter_sales_channel_ids` = NULL
WHERE #table#.`id` IN (:ids)
SQL;

        $parameters = [
            'ids' => Uuid::fromHexToBytesList($ids),
            'states' => [NewsletterSubscribeRoute::STATUS_DIRECT, NewsletterSubscribeRoute::STATUS_OPT_IN],
        ];

        $replacement = [
            '#table#' => $reverseUpdate ? '`customer`' : '`newsletter_recipient`',
        ];

        $sql = str_replace(
            array_keys($replacement),
            array_values($replacement),
            $tableTemplate
        );

        $resetSql = str_replace(
            array_keys($replacement),
            array_values($replacement),
            $resetTemplate
        );

        RetryableQuery::retryable($this->connection, function () use ($resetSql, $parameters): void {
            $this->connection->executeUpdate(
                $resetSql,
                $parameters,
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
        });

        RetryableQuery::retryable($this->connection, function () use ($sql, $parameters): void {
            $this->connection->executeUpdate(
                $sql,
                $parameters,
                ['ids' => Connection::PARAM_STR_ARRAY, 'states' => Connection::PARAM_STR_ARRAY]
            );
        });
    }

    /**
     * @param array<string> $ids
     */
    public function delete(array $ids): void
    {
        $sqlTemplate = <<<'SQL'
SELECT `customer`.`id`
FROM `customer`
WHERE #expressions#
SQL;

        $expressions = [];
        foreach ($ids as $id) {
            $expressions[] = 'JSON_EXTRACT(`customer`.`newsletter_sales_channel_ids`, \'$."' . $id . '"\') IS NOT NULL';
        }

        $replacement = [
            '#expressions#' => implode(' OR ', $expressions),
        ];

        $sql = str_replace(
            array_keys($replacement),
            array_values($replacement),
            $sqlTemplate
        );

        $customerIds = RetryableQuery::retryable($this->connection, function () use ($sql): array {
            return $this->connection->fetchFirstColumn($sql);
        });

        if (empty($customerIds)) {
            return;
        }

        $this->update(Uuid::fromBytesToHexList($customerIds), true);
    }

    /**
     * @deprecated tag:v6.5.0 - will be removed
     *
     * @param array<string> $ids
     */
    public function updateCustomerEmailRecipient(array $ids): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $ids = array_unique($ids);

        $customers = $this->connection->fetchAllAssociative(
            'SELECT newsletter_sales_channel_ids, email FROM customer WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $parameters = [];

        foreach ($customers as $customer) {
            if (!$customer['newsletter_sales_channel_ids']) {
                continue;
            }

            $parameters[] = [
                'newsletter_ids' => array_keys(
                    json_decode((string) $customer['newsletter_sales_channel_ids'], true)
                ),
                'email' => $customer['email'],
            ];
        }

        if (empty($parameters)) {
            return;
        }

        foreach ($parameters as $parameter) {
            RetryableQuery::retryable($this->connection, function () use ($parameter): void {
                $this->connection->executeUpdate(
                    'UPDATE newsletter_recipient SET email = (:email) WHERE id IN (:ids)',
                    ['ids' => Uuid::fromHexToBytesList($parameter['newsletter_ids']), 'email' => $parameter['email']],
                    ['ids' => Connection::PARAM_STR_ARRAY],
                );
            });
        }
    }

    /**
     * @param array<string> $ids
     */
    public function updateCustomersRecipient(array $ids): void
    {
        $ids = array_unique($ids);

        $customers = $this->connection->fetchAllAssociative(
            'SELECT newsletter_sales_channel_ids, email, first_name, last_name FROM customer WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $parameters = [];

        foreach ($customers as $customer) {
            if (!$customer['newsletter_sales_channel_ids']) {
                continue;
            }

            $parameters[] = [
                'newsletter_ids' => array_keys(
                    json_decode((string) $customer['newsletter_sales_channel_ids'], true)
                ),
                'email' => $customer['email'],
                'first_name' => $customer['first_name'],
                'last_name' => $customer['last_name'],
            ];
        }

        if (empty($parameters)) {
            return;
        }

        foreach ($parameters as $parameter) {
            RetryableQuery::retryable($this->connection, function () use ($parameter): void {
                $this->connection->executeUpdate(
                    'UPDATE newsletter_recipient SET email = (:email), first_name = (:firstName), last_name = (:lastName) WHERE id IN (:ids)',
                    [
                        'ids' => Uuid::fromHexToBytesList($parameter['newsletter_ids']),
                        'email' => $parameter['email'],
                        'firstName' => $parameter['first_name'],
                        'lastName' => $parameter['last_name'],
                    ],
                    ['ids' => Connection::PARAM_STR_ARRAY],
                );
            });
        }
    }
}
