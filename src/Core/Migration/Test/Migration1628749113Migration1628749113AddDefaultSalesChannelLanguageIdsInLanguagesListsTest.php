<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1628749113Migration1628749113AddDefaultSalesChannelLanguageIdsInLanguagesLists;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('core')]
class Migration1628749113Migration1628749113AddDefaultSalesChannelLanguageIdsInLanguagesListsTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const CREATED_SALES_CHANNELS = 10;

    private Connection $connection;

    /**
     * @var array<string, string>
     */
    private array $defaultLanguageIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->createSalesChannelsWithoutLanguages();

        $salesChannelIds = array_keys($this->defaultLanguageIds);
        $languages = $this->fetchMappedLanguageData($salesChannelIds);
        static::assertEmpty($languages);
    }

    public function testSalesChannelLanguagesWillBeCreated(): void
    {
        $migration = new Migration1628749113Migration1628749113AddDefaultSalesChannelLanguageIdsInLanguagesLists();
        $migration->update($this->connection);

        $salesChannelIds = array_keys($this->defaultLanguageIds);
        $languages = $this->fetchMappedLanguageData($salesChannelIds);

        foreach ($languages as $language) {
            static::assertTrue(\in_array($language['languageId'], $language['languages'], true));
        }
    }

    public function testMigrationDoesNothingWhenAlreadyExisting(): void
    {
        $migration = new Migration1628749113Migration1628749113AddDefaultSalesChannelLanguageIdsInLanguagesLists();
        $migration->update($this->connection);

        $salesChannelIds = array_keys($this->defaultLanguageIds);

        static::assertSame(self::CREATED_SALES_CHANNELS, $this->countSalesChannelLanguages($salesChannelIds));

        $migration->update($this->connection);

        static::assertSame(self::CREATED_SALES_CHANNELS, $this->countSalesChannelLanguages($salesChannelIds));
    }

    /**
     * @param list<string> $ids
     */
    private function countSalesChannelLanguages(array $ids): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(sales_channel_id)
            FROM sales_channel_language
            WHERE sales_channel_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::STRING]
        );
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, array{languageId: string, languages: list<string>}>
     */
    private function fetchMappedLanguageData(array $ids): array
    {
        $raw = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(sc.id)) AS `id`, LOWER(HEX(sc.language_id)) as `defaultLanguage`, LOWER(HEX(scl.language_id)) AS `language`
            FROM sales_channel sc
            INNER JOIN sales_channel_language scl
            ON sc.id = scl.sales_channel_id
            WHERE sc.id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::STRING]
        );

        $languages = [];
        foreach ($raw as $record) {
            $id = (string) $record['id'];

            $languages[$id]['languageId'] = $record['defaultLanguage'];
            $languages[$id]['languages'][] = $record['language'];
        }

        return $languages;
    }

    private function createSalesChannelsWithoutLanguages(): void
    {
        $data = [];
        for ($i = 0; $i < self::CREATED_SALES_CHANNELS; ++$i) {
            if ($i % 2 === 0) {
                $data[] = $this->getSalesChannelDataBase();

                continue;
            }

            $data[] = $this->getSalesChannelDataBase($this->getDeDeLanguageId());
        }

        $this->getContainer()->get('sales_channel.repository')
            ->create($data, Context::createDefaultContext());

        $this->deleteAllLanguagesLists();
    }

    private function deleteAllLanguagesLists(): void
    {
        $this->connection->executeStatement('DELETE FROM sales_channel_language ');
    }

    /**
     * @return array<string, mixed>
     */
    private function getSalesChannelDataBase(string $languageId = Defaults::LANGUAGE_SYSTEM): array
    {
        $id = Uuid::randomHex();
        $this->defaultLanguageIds[$id] = $languageId;

        return [
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'languageId' => $languageId,
            'languages' => [['id' => $languageId]],
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'name' => 'first sales-channel',
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
        ];
    }
}
