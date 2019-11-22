<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\ApiConverter;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Converter\ApiConverter;
use Shopware\Core\Framework\Uuid\Uuid;

class ConverterV4 extends ApiConverter
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getApiVersion(): int
    {
        return 4;
    }

    protected function getDeprecations(): array
    {
        return [
            '_test_bundle' => [
                'pseudoPrice',
            ],
        ];
    }

    protected function getNewFields(): array
    {
        return [
            '_test_bundle_price' => [
                'pseudoPrice',
            ],
        ];
    }

    /**
     * @return callable[]
     */
    protected function getConverterFunctions(): array
    {
        return [
            '_test_bundle' => function (array $payload): array {
                if (array_key_exists('pseudoPrice', $payload)) {
                    if (array_key_exists('prices', $payload)) {
                        foreach ($payload['prices'] as $key => $price) {
                            if (($price['quantityStart'] ?? 1) === 0) {
                                $price['pseudoPrice'] = $payload['pseudoPrice'];
                                $payload['prices'][$key] = $price;
                                unset($payload['pseudoPrice']);

                                return $payload;
                            }
                        }
                    }

                    if (array_key_exists('id', $payload)) {
                        $priceId = $this->getFirstPriceIdForBundleId($payload['id']);

                        if ($priceId) {
                            $payload['prices'][] = [
                                'id' => $priceId,
                                'pseudoPrice' => $payload['pseudoPrice'],
                            ];
                            unset($payload['pseudoPrice']);
                        }
                    }
                }

                return $payload;
            },
        ];
    }

    private function getFirstPriceIdForBundleId(string $bundleId): ?string
    {
        /** @var string|false $priceId */
        $priceId = $this->connection->fetchColumn(
            '
            SELECT `id` 
            FROM _test_bundle_price
            WHERE `bundle_id` = :bundleId AND `quantity_start` = 0
        ',
            ['bundleId' => Uuid::fromHexToBytes($bundleId)]
        );

        if (!$priceId) {
            return null;
        }

        return Uuid::fromBytesToHex($priceId);
    }
}
