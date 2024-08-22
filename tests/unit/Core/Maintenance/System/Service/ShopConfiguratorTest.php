<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\MaintenanceException;
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
            static::assertSame(
                'INSERT INTO `system_config` (`id`, `configuration_key`, `configuration_value`, `sales_channel_id`, `created_at`)
            VALUES (:id, :key, :value, NULL, NOW())
            ON DUPLICATE KEY UPDATE
                `configuration_value` = :value,
                `updated_at` = NOW()',
                trim($sql)
            );

            static::assertArrayHasKey('id', $parameters);
            static::assertArrayHasKey('key', $parameters);
            static::assertArrayHasKey('value', $parameters);

            if ($parameters['key'] === 'core.basicInformation.shopName') {
                static::assertSame('{"_value":"test-shop"}', $parameters['value']);
            } else {
                static::assertSame('core.basicInformation.email', $parameters['key']);
                static::assertSame('{"_value":"shop@test.com"}', $parameters['value']);
            }
        });

        $this->shopConfigurator->updateBasicInformation('test-shop', 'shop@test.com');
    }

    public function testSetDefaultLanguageWithoutCurrentLocale(): void
    {
        $this->expectException(MaintenanceException::class);
        $this->expectExceptionMessage('Default language locale not found');

        $this->connection->expects(static::once())->method('fetchAssociative')->willReturnCallback(function (string $sql, array $parameters): ?array {
            static::assertSame(
                'SELECT locale.id, locale.code
             FROM language
             INNER JOIN locale ON translation_code_id = locale.id
             WHERE language.id = :languageId',
                trim($sql)
            );

            static::assertArrayHasKey('languageId', $parameters);
            static::assertSame(Defaults::LANGUAGE_SYSTEM, Uuid::fromBytesToHex($parameters['languageId']));

            return null;
        });

        $this->shopConfigurator->setDefaultLanguage('vi-VN');
    }

    public function testSetDefaultLanguageMatchCurrentLocale(): void
    {
        $currentLocaleId = Uuid::randomHex();

        $this->connection->expects(static::once())->method('fetchAssociative')->willReturnCallback(function (string $sql, array $parameters) use ($currentLocaleId) {
            static::assertSame(
                'SELECT locale.id, locale.code
             FROM language
             INNER JOIN locale ON translation_code_id = locale.id
             WHERE language.id = :languageId',
                trim($sql)
            );

            static::assertArrayHasKey('languageId', $parameters);
            static::assertSame(Defaults::LANGUAGE_SYSTEM, Uuid::fromBytesToHex($parameters['languageId']));

            return ['id' => $currentLocaleId, 'code' => 'vi-VN'];
        });

        $this->connection->expects(static::once())->method('fetchOne')->willReturnCallback(function (string $sql, array $parameters) use ($currentLocaleId) {
            static::assertSame(
                'SELECT locale.id FROM  locale WHERE LOWER(locale.code) = LOWER(:iso)',
                trim($sql)
            );

            static::assertArrayHasKey('iso', $parameters);
            static::assertSame('vi-VN', $parameters['iso']);

            return $currentLocaleId;
        });

        $this->connection->expects(static::never())->method('executeStatement');
        $this->connection->expects(static::never())->method('prepare');

        $this->shopConfigurator->setDefaultLanguage('vi_VN');
    }

    public function testSetDefaultLanguageWithUnavailableIso(): void
    {
        $this->expectException(MaintenanceException::class);
        $this->expectExceptionMessage('Locale with iso-code "vi-VN" not found');

        $currentLocaleId = Uuid::randomHex();

        $this->connection->expects(static::once())->method('fetchAssociative')->willReturnCallback(function (string $sql, array $parameters) use ($currentLocaleId) {
            static::assertSame(
                'SELECT locale.id, locale.code
             FROM language
             INNER JOIN locale ON translation_code_id = locale.id
             WHERE language.id = :languageId',
                trim($sql)
            );

            static::assertArrayHasKey('languageId', $parameters);
            static::assertSame(Defaults::LANGUAGE_SYSTEM, Uuid::fromBytesToHex($parameters['languageId']));

            return ['id' => $currentLocaleId, 'code' => 'vi-VN'];
        });

        $this->connection->expects(static::once())->method('fetchOne')->willReturnCallback(function (string $sql, array $parameters) {
            static::assertSame(
                'SELECT locale.id FROM  locale WHERE LOWER(locale.code) = LOWER(:iso)',
                trim($sql)
            );

            static::assertArrayHasKey('iso', $parameters);
            static::assertSame('vi-VN', $parameters['iso']);

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
            static::assertSame(
                'SELECT locale.id, locale.code
             FROM language
             INNER JOIN locale ON translation_code_id = locale.id
             WHERE language.id = :languageId',
                trim($sql)
            );

            static::assertArrayHasKey('languageId', $parameters);
            static::assertSame(Defaults::LANGUAGE_SYSTEM, Uuid::fromBytesToHex($parameters['languageId']));

            return ['id' => $currentLocaleId, 'code' => 'en-GB'];
        });

        $viLocaleId = Uuid::randomHex();

        $this->connection->expects(static::atLeast(2))->method('fetchOne')->willReturn($viLocaleId);

        $methodReturns = array_values(array_filter([$expectedMissingTranslations, $expectedStateTranslations], static fn (array $item) => $item !== []));

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
        $insertCallback = static function (string $table, array $parameters): void {
            static::assertSame('country_state_translation', $table);
            static::assertArrayHasKey('language_id', $parameters);
            static::assertArrayHasKey('name', $parameters);
            static::assertArrayHasKey('country_state_id', $parameters);
            static::assertArrayHasKey('created_at', $parameters);
            static::assertSame(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), $parameters['language_id']);
        };

        yield 'empty country state translations' => [
            'expectedStateTranslations' => [],
            'expectedMissingTranslations' => [],
            'expectedInsertCall' => 0,
            'insertCallback' => $insertCallback,
        ];

        yield 'none missing default translations' => [
            'expectedStateTranslations' => [
                'USA' => 'United State',
                'VNA' => 'Viet Nam',
            ],
            'expectedMissingTranslations' => [],
            'expectedInsertCall' => 0,
            'insertCallback' => $insertCallback,
        ];

        yield 'missing default translations' => [
            'expectedStateTranslations' => [
                'USA' => 'United State',
                'VNA' => 'Viet Nam',
            ],
            'expectedMissingTranslations' => [
                'id_vna' => 'VNA',
            ],
            'expectedInsertCall' => 1,
            'insertCallback' => $insertCallback,
        ];

        yield 'correcting german translations' => [
            'expectedStateTranslations' => [
                'DE-TH' => 'Thuringia',
                'DE-NW' => 'North Rhine-Westphalia',
                'DE-RP' => 'Rhineland-Palatinate',
            ],
            'expectedMissingTranslations' => [
                'id_de_th' => 'DE-TH',
                'id_de_nw' => 'DE-NW',
                'id_de_rp' => 'DE-RP',
            ],
            'expectedInsertCall' => 3,
            /**
             * @param array<string, string> $parameters
             */
            'insertCallback' => function (string $table, array $parameters): void {
                static::assertSame('country_state_translation', $table);
                static::assertArrayHasKey('language_id', $parameters);
                static::assertArrayHasKey('name', $parameters);
                static::assertArrayHasKey('country_state_id', $parameters);
                static::assertArrayHasKey('created_at', $parameters);
                static::assertSame(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), $parameters['language_id']);

                $countryStateId = $parameters['country_state_id'];

                static::assertContains($countryStateId, [
                    'id_de_th',
                    'id_de_nw',
                    'id_de_rp',
                ]);

                if ($countryStateId === 'id_de_th') {
                    static::assertSame('Thüringen', $parameters['name']);
                }

                if ($countryStateId === 'id_de_nw') {
                    static::assertSame('Nordrhein-Westfalen', $parameters['name']);
                }

                if ($countryStateId === 'id_de_rp') {
                    static::assertSame('Rheinland-Pfalz', $parameters['name']);
                }
            },
        ];

        yield 'missing default translations but not available' => [
            'expectedStateTranslations' => [
                'USA' => 'United State',
                'VNA' => 'Viet Nam',
            ],
            'expectedMissingTranslations' => [
                'id_jpn' => 'JPN',
            ],
            'expectedInsertCall' => 0,
            'insertCallback' => $insertCallback,
        ];
    }
}
