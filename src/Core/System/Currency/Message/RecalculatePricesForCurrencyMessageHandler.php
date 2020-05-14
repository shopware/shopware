<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Message;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\Framework\Uuid\Uuid;

class RecalculatePricesForCurrencyMessageHandler extends AbstractMessageHandler
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CacheClearer
     */
    private $cacheClearer;

    public function __construct(Connection $connection, CacheClearer $cacheClearer)
    {
        $this->connection = $connection;
        $this->cacheClearer = $cacheClearer;
    }

    /**
     * @param RecalculatePricesForCurrencyMessage $message
     */
    public function handle($message): void
    {
        $updateQuery = <<<SQL
UPDATE #table#
SET #field# = JSON_SET(
    #field#,
    '$.cb7d2554b0ce847cd82f3ac9bd1c0dfca.net', JSON_EXTRACT(#field#, '$.cb7d2554b0ce847cd82f3ac9bd1c0dfca.net') * :factor,
    '$.cb7d2554b0ce847cd82f3ac9bd1c0dfca.gross', JSON_EXTRACT(#field#, '$.cb7d2554b0ce847cd82f3ac9bd1c0dfca.gross') * :factor
)
WHERE id IN (:ids)
SQL;
        foreach ($message->getFields() as $field) {
            $execQuery = str_replace(['#table#', '#field#'], [$message->getTable(), $field], $updateQuery);

            $this->connection->executeQuery(
                $execQuery,
                [
                    'ids' => Uuid::fromHexToBytesList($message->getIds()),
                    'factor' => $message->getMultiplyWith(),
                ],
                [
                    'ids' => Connection::PARAM_STR_ARRAY,
                ]
            );
        }
    }

    public static function getHandledMessages(): iterable
    {
        return [
            RecalculatePricesForCurrencyMessage::class,
        ];
    }
}
