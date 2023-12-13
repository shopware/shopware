<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\System\Exception\ShopConfigurationException;
use Shopware\Core\Maintenance\System\Service\ShopConfigurator;

/**
 * @internal
 */
#[CoversClass(ShopConfigurator::class)]
class ShopConfiguratorTest extends TestCase
{
    private ShopConfigurator $shopConfigurator;

    /**
     * @var Connection&MockObject
     */
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->shopConfigurator = new ShopConfigurator($this->connection);
    }

    public function testUpdateBasicInformation(): void
    {
        $this->connection->expects(static::exactly(2))->method('executeStatement')->willReturnCallback(function (string $sql, array $parameters): void {
            static::assertEquals(
                trim($sql),
                'INSERT INTO `system_config` (`id`, `configuration_key`, `configuration_value`, `sales_channel_id`, `created_at`)
            VALUES (:id, :key, :value, NULL, NOW())
            ON DUPLICATE KEY UPDATE
                `configuration_value` = :value,
                `updated_at` = NOW()'
            );

            static::assertArrayHasKey('id', $parameters);
            static::assertArrayHasKey('key', $parameters);
            static::assertArrayHasKey('value', $parameters);

            if ($parameters['key'] === 'core.basicInformation.shopName') {
                static::assertEquals('{"_value":"test-shop"}', $parameters['value']);
            } else {
                static::assertEquals('core.basicInformation.email', $parameters['key']);
                static::assertEquals('{"_value":"shop@test.com"}', $parameters['value']);
            }
        });

        $this->shopConfigurator->updateBasicInformation('test-shop', 'shop@test.com');
    }

    public function testSetDefaultLanguageWithoutCurrentLocale(): void
    {
        static::expectException(ShopConfigurationException::class);
        static::expectExceptionMessage('Default language locale not found');

        $this->connection->expects(static::once())->method('fetchAssociative')->willReturnCallback(function (string $sql, array $parameters): ?array {
            static::assertEquals(
                trim($sql),
                'SELECT locale.id, locale.code
             FROM language
             INNER JOIN locale ON translation_code_id = locale.id
             WHERE language.id = :languageId'
            );

            static::assertArrayHasKey('languageId', $parameters);
            static::assertEquals(Defaults::LANGUAGE_SYSTEM, Uuid::fromBytesToHex($parameters['languageId']));

            return null;
        });

        $this->shopConfigurator->setDefaultLanguage('vi-VN');
    }

    public function testSetDefaultLanguageMatchCurrentLocale(): void
    {
        $currentLocaleId = Uuid::randomHex();

        $this->connection->expects(static::once())->method('fetchAssociative')->willReturnCallback(function (string $sql, array $parameters) use ($currentLocaleId) {
            static::assertEquals(
                trim($sql),
                'SELECT locale.id, locale.code
             FROM language
             INNER JOIN locale ON translation_code_id = locale.id
             WHERE language.id = :languageId'
            );

            static::assertArrayHasKey('languageId', $parameters);
            static::assertEquals(Defaults::LANGUAGE_SYSTEM, Uuid::fromBytesToHex($parameters['languageId']));

            return ['id' => $currentLocaleId, 'code' => 'vi-VN'];
        });

        $this->connection->expects(static::once())->method('fetchOne')->willReturnCallback(function (string $sql, array $parameters) use ($currentLocaleId) {
            static::assertEquals(
                trim($sql),
                'SELECT locale.id FROM  locale WHERE LOWER(locale.code) = LOWER(:iso)'
            );

            static::assertArrayHasKey('iso', $parameters);
            static::assertEquals('vi-VN', $parameters['iso']);

            return $currentLocaleId;
        });

        $this->connection->expects(static::never())->method('executeStatement');
        $this->connection->expects(static::never())->method('prepare');

        $this->shopConfigurator->setDefaultLanguage('vi_VN');
    }

    public function testSetDefaultLanguageWithUnavailableIso(): void
    {
        static::expectException(ShopConfigurationException::class);
        static::expectExceptionMessage('Locale with iso-code vi-VN not found');

        $currentLocaleId = Uuid::randomHex();

        $this->connection->expects(static::once())->method('fetchAssociative')->willReturnCallback(function (string $sql, array $parameters) use ($currentLocaleId) {
            static::assertEquals(
                trim($sql),
                'SELECT locale.id, locale.code
             FROM language
             INNER JOIN locale ON translation_code_id = locale.id
             WHERE language.id = :languageId'
            );

            static::assertArrayHasKey('languageId', $parameters);
            static::assertEquals(Defaults::LANGUAGE_SYSTEM, Uuid::fromBytesToHex($parameters['languageId']));

            return ['id' => $currentLocaleId, 'code' => 'vi-VN'];
        });

        $this->connection->expects(static::once())->method('fetchOne')->willReturnCallback(function (string $sql, array $parameters) {
            static::assertEquals(
                trim($sql),
                'SELECT locale.id FROM  locale WHERE LOWER(locale.code) = LOWER(:iso)'
            );

            static::assertArrayHasKey('iso', $parameters);
            static::assertEquals('vi-VN', $parameters['iso']);

            return null;
        });

        $this->shopConfigurator->setDefaultLanguage('vi_VN');
    }

    /**
     * @param array<string, string> $expectedStateTranslations
     * @param array<string, string> $expectedMissingTranslations
     * @param callable(string, array<string, string>): void $insertCallback
     */
    #[DataProvider('countryStateTranslationsProvider')]
    public function testSetDefaultLanguageShouldAddMissingCountryStatesTranslations(
        array $expectedStateTranslations,
        array $expectedMissingTranslations,
        int $expectedInsertCall,
        callable $insertCallback
    ): void {
        $currentLocaleId = Uuid::randomHex();

        $this->connection->expects(static::once())->method('fetchAssociative')->willReturnCallback(function (string $sql, array $parameters) use ($currentLocaleId) {
            static::assertEquals(
                trim($sql),
                'SELECT locale.id, locale.code
             FROM language
             INNER JOIN locale ON translation_code_id = locale.id
             WHERE language.id = :languageId'
            );

            static::assertArrayHasKey('languageId', $parameters);
            static::assertEquals(Defaults::LANGUAGE_SYSTEM, Uuid::fromBytesToHex($parameters['languageId']));

            return ['id' => $currentLocaleId, 'code' => 'en-GB'];
        });

        $viLocaleId = Uuid::randomHex();

        $this->connection->expects(static::atLeast(2))->method('fetchOne')->willReturn($viLocaleId);

        $methodReturns = array_values(array_filter([$expectedMissingTranslations, $expectedStateTranslations], fn (array $item) => $item !== []));

        $methodCalls = \count($methodReturns);

        $this->connection->expects(static::atLeast($methodCalls))->method('fetchAllKeyValue')->willReturnOnConsecutiveCalls($expectedStateTranslations, $expectedMissingTranslations);

        $this->connection->expects(static::exactly($expectedInsertCall))->method('insert')->willReturnCallback($insertCallback);
        $this->shopConfigurator->setDefaultLanguage('de_DE');
    }

    /**
     * @return iterable<string, array<string, mixed>>
     */
    public static function countryStateTranslationsProvider(): iterable
    {
        /**
         * @param array<string, string> $parameters
         */
        $insertCallback = function (string $table, array $parameters): void {
            static::assertEquals('country_state_translation', $table);
            static::assertArrayHasKey('language_id', $parameters);
            static::assertArrayHasKey('name', $parameters);
            static::assertArrayHasKey('country_state_id', $parameters);
            static::assertArrayHasKey('created_at', $parameters);
            static::assertEquals(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), $parameters['language_id']);
        };

        yield 'empty country state translations' => [
            'country state translations' => [],
            'missing translations' => [],
            'expected insert call' => 0,
            'expected insert callback' => $insertCallback,
        ];

        yield 'none missing default translations' => [
            'country state translations' => [
                'USA' => 'United State',
                'VNA' => 'Viet Nam',
            ],
            'missing translations' => [],
            'expected insert call' => 0,
            'expected insert callback' => $insertCallback,
        ];

        yield 'missing default translations' => [
            'country state translations' => [
                'USA' => 'United State',
                'VNA' => 'Viet Nam',
            ],
            'missing translations' => [
                'id_vna' => 'VNA',
            ],
            'expected insert call' => 1,
            'expected insert callback' => $insertCallback,
        ];

        yield 'correcting german translations' => [
            'country state translations' => [
                'DE-TH' => 'Thuringia',
                'DE-NW' => 'North Rhine-Westphalia',
                'DE-RP' => 'Rhineland-Palatinate',
            ],
            'missing translations' => [
                'id_de_th' => 'DE-TH',
                'id_de_nw' => 'DE-NW',
                'id_de_rp' => 'DE-RP',
            ],
            'expected insert call' => 3,
            /**
             * @param array<string, string> $parameters
             */
            'expected insert callback' => function (string $table, array $parameters): void {
                static::assertEquals('country_state_translation', $table);
                static::assertArrayHasKey('language_id', $parameters);
                static::assertArrayHasKey('name', $parameters);
                static::assertArrayHasKey('country_state_id', $parameters);
                static::assertArrayHasKey('created_at', $parameters);
                static::assertEquals(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), $parameters['language_id']);

                $countryStateId = $parameters['country_state_id'];

                static::assertTrue(\in_array($countryStateId, [
                    'id_de_th',
                    'id_de_nw',
                    'id_de_rp',
                ], true));

                if ($countryStateId === 'id_de_th') {
                    static::assertEquals('Thüringen', $parameters['name']);
                }

                if ($countryStateId === 'id_de_nw') {
                    static::assertEquals('Nordrhein-Westfalen', $parameters['name']);
                }

                if ($countryStateId === 'id_de_rp') {
                    static::assertEquals('Rheinland-Pfalz', $parameters['name']);
                }
            },
        ];

        yield 'missing default translations but not available' => [
            'country state translations' => [
                'USA' => 'United State',
                'VNA' => 'Viet Nam',
            ],
            'missing translations' => [
                'id_jpn' => 'JPN',
            ],
            'expected insert call' => 0,
            'expected insert callback' => $insertCallback,
        ];
    }
}
