<?php declare(strict_types=1);

namespace Shopware\Administration\Test\Migration;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Migration\V6_4\Migration1636121186CopySalesChannelIdsIntoUserConfig;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationDefinition;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigDefinition;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class Migration1636121186CopySalesChannelIdsIntoUserConfigTest extends TestCase
{
    use ImportTranslationsTrait;
    use IntegrationTestBehaviour;

    private const MAX_RESULTS = 7;

    private Connection $connection;

    private array $languages;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()
            ->get(Connection::class);

        $this->cleanUpSalesChannels();

        $this->languages = $this->fetchLanguages();

        $this->createSalesChannels();
        $this->createUserForEachLanguage();
    }

    public function testMigrationCopiesSalesChannelIdsIntoUserConfig(): void
    {
        $migration = $this->getMigration();
        $migration->update($this->connection);

        $expectedIds = $this->fetchExpectedSalesChannelIds(array_values($this->languages));
        $configs = $this->fetchConfigs();

        foreach ($configs as $locale => $config) {
            $language = $this->languages[$locale];

            $actual = json_decode((string) $config, null, 512, \JSON_THROW_ON_ERROR);
            $expected = \array_slice($expectedIds[$language], 0, self::MAX_RESULTS);

            static::assertSame($expected, $actual);
            static::assertCount(self::MAX_RESULTS, $actual);
        }
    }

    public function testMigrationCopiesAlphabeticallyCorrect(): void
    {
        $migration = $this->getMigration();
        $migration->update($this->connection);

        $configs = $this->fetchConfigs();

        foreach ($configs as $locale => $config) {
            $language = $this->languages[$locale];

            $names = $this->fetchSalesChannelNames(
                Uuid::fromHexToBytesList(json_decode((string) $config, null, 512, \JSON_THROW_ON_ERROR)),
                Uuid::fromHexToBytes($language)
            );

            if ($language === Defaults::LANGUAGE_SYSTEM) {
                static::assertSame([
                    'A - english',
                    'B - english',
                    'BC - english',
                    'BE - english',
                    'C - english',
                    'D - english',
                    'H - english',
                ], $names);

                return;
            }

            static::assertSame([
                'A - german',
                'B - german',
                'BC - german',
                'BE - german',
                'C - german',
                'D - german',
                'H - german',
            ], $names);
        }
    }

    /**
     * @param string[] $salesChannelIds
     */
    private function fetchSalesChannelNames(array $salesChannelIds, string $languageId): array
    {
        return $this->connection->createQueryBuilder()
            ->select('name')
            ->from(SalesChannelTranslationDefinition::ENTITY_NAME)
            ->where('sales_channel_id IN (:salesChannelIds)')
            ->andWhere('language_id = :languageId')
            ->setParameter('salesChannelIds', $salesChannelIds, ArrayParameterType::STRING)
            ->setParameter('languageId', $languageId)
            ->addOrderBy('name', 'ASC')
            ->executeQuery()
            ->fetchFirstColumn();
    }

    private function createUserForEachLanguage(): void
    {
        $insert = [];
        $locales = array_keys($this->languages);

        foreach ($locales as $locale) {
            $insert[] = $this->getUserFixture(Uuid::randomHex(), $locale);
        }

        $this->getContainer()->get('user.repository')
            ->create($insert, Context::createDefaultContext());
    }

    private function getUserFixture(string $username, string $localeId): array
    {
        return [
            'username' => $username,
            'password' => 'shopware',
            'firstName' => 'admin',
            'lastName' => 'user',
            'email' => $username . '@test.com',
            'active' => true,
            'admin' => true,
            'localeId' => $localeId,
        ];
    }

    private function fetchLanguages(): array
    {
        $all = $this->connection->createQueryBuilder()
            ->select('LOWER(HEX(language.locale_id))')
            ->addSelect('LOWER(HEX(language.id))')
            ->from(LanguageDefinition::ENTITY_NAME, 'language')
            ->innerJoin(
                'language',
                LocaleDefinition::ENTITY_NAME,
                'locale',
                'locale.id = language.locale_id'
            )
            ->where('locale.code = "de-DE"')
            ->orWhere('locale.code = "en-GB"')
            ->executeQuery()
            ->fetchAllAssociative();

        return FetchModeHelper::keyPair($all);
    }

    private function fetchConfigs(): array
    {
        $all = $this->connection->createQueryBuilder()
            ->select('LOWER(HEX(user.locale_id))')
            ->addSelect('config.value')
            ->from(UserConfigDefinition::ENTITY_NAME, 'config')
            ->innerJoin(
                'config',
                UserDefinition::ENTITY_NAME,
                'user',
                'user.id = config.user_id'
            )
            ->where('config.key = "sales-channel-favorites"')
            ->executeQuery()
            ->fetchAllAssociative();

        return FetchModeHelper::keyPair($all);
    }

    private function fetchExpectedSalesChannelIds(array $languages): array
    {
        $all = $this->connection->createQueryBuilder()
            ->select('LOWER(HEX(sales_channel_id)) AS salesChannelId')
            ->addSelect('LOWER(HEX(language_id)) AS languageId')
            ->from(SalesChannelTranslationDefinition::ENTITY_NAME)
            ->where('language_id IN (:languages)')
            ->orderBy('name', 'ASC')
            ->setParameter('languages', Uuid::fromHexToBytesList($languages), ArrayParameterType::STRING)
            ->executeQuery()
            ->fetchAllAssociative();

        $salesChannels = [];
        foreach ($all as $ids) {
            $salesChannels[$ids['languageId']][] = $ids['salesChannelId'];
        }

        return $salesChannels;
    }

    private function cleanUpSalesChannels(): void
    {
        $this->connection->executeStatement('DELETE FROM sales_channel');
    }

    private function createSalesChannels(): void
    {
        $names = ['Z', 'A', 'C', 'D', 'B', 'J', 'H', 'T', 'BE', 'BC'];

        $payment = $this->getValidPaymentMethodId();
        $shipping = $this->getValidShippingMethodId();
        $category = $this->getValidCategoryId();
        $country = $this->getValidCountryId(null);

        $languages = [
            $this->getLanguageIds($this->connection, 'de-DE')[0],
            Defaults::LANGUAGE_SYSTEM,
        ];

        $insert = [];
        foreach ($names as $name) {
            $insert[] = $this->getSalesChannelFixture([
                'paymentMethodId' => $payment,
                'shippingMethodId' => $shipping,
                'navigationCategoryId' => $category,
                'countryId' => $country,
                'shippingMethods' => [['id' => $shipping]],
                'paymentMethods' => [['id' => $payment]],
                'countries' => [['id' => $country]],
                'name' => $name . ' - english',
                'translations' => [
                    $languages[0] => [
                        'name' => $name . ' - german',
                    ],
                    $languages[1] => [
                        'name' => $name . ' - english',
                    ],
                ],
            ]);
        }

        $this->getContainer()->get('sales_channel.repository')
            ->create($insert, Context::createDefaultContext());
    }

    private function getSalesChannelFixture(array $merge): array
    {
        $data = [
            'id' => Uuid::randomHex(),
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
        ];

        return array_merge_recursive($data, $merge);
    }

    private function getMigration(): Migration1636121186CopySalesChannelIdsIntoUserConfig
    {
        return new Migration1636121186CopySalesChannelIdsIntoUserConfig();
    }
}
