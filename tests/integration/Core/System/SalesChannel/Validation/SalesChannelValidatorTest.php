<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\Validation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const INSERT_VALIDATION_MESSAGE = 'The sales channel with id "%s" does not have a default sales channel language id in the language list.';
    private const UPDATE_VALIDATION_MESSAGE = 'Cannot update default language id because the given id is not in the language list of sales channel with id "%s"';
    private const DELETE_VALIDATION_MESSAGE = 'Cannot delete default language id from language list of the sales channel with id "%s".';

    private const CURRENCY_INSERT_VALIDATION_MESSAGE = 'The sales channel with id "%s" does not have a default sales channel currency id in the currency list.';
    private const CURRENCY_UPDATE_VALIDATION_MESSAGE = 'Cannot update default currency id because the given id is not in the currency list of sales channel with id "%s"';
    private const CURRENCY_DELETE_VALIDATION_MESSAGE = 'Cannot delete default currency id from currency list of the sales channel with id "%s".';

    /**
     * @param list<array{0: string, 1: string, 2?: list<string>}> $inserts
     * @param list<string> $invalids
     * @param list<array{id: string}> $valids
     */
    #[DataProvider('getInsertValidationProvider')]
    public function testInsertValidation(array $inserts, array $invalids = [], array $valids = []): void
    {
        $exception = null;

        $deDeLanguageId = $this->getDeDeLanguageId();
        $salesChannelCreationData = [];
        foreach ($inserts as $insert) {
            foreach ($insert[2] ?? [] as $key => $language) {
                if ($language === 'de-DE') {
                    $insert[2][$key] = $deDeLanguageId;
                }
            }

            $salesChannelCreationData[] = $this->getSalesChannelData(...$insert);
        }

        try {
            $this->getSalesChannelRepository()->create($salesChannelCreationData, Context::createDefaultContext());
        } catch (WriteException $exception) {
            // nth
        }

        if (!$invalids) {
            static::assertNull($exception);

            $this->getSalesChannelRepository()->delete($valids, Context::createDefaultContext());

            return;
        }

        static::assertInstanceOf(WriteException::class, $exception);
        $message = $exception->getMessage();

        foreach ($invalids as $invalid) {
            $expectedError = \sprintf(self::INSERT_VALIDATION_MESSAGE, $invalid);
            static::assertStringContainsString($expectedError, $message);
        }

        $this->getSalesChannelRepository()->delete($valids, Context::createDefaultContext());
    }

    public static function getInsertValidationProvider(): \Generator
    {
        $valid1 = Uuid::randomHex();

        yield 'Payload with single valid entry' => [
            [
                [$valid1, Defaults::LANGUAGE_SYSTEM, ['de-DE', Defaults::LANGUAGE_SYSTEM]],
            ],
            [],
            [
                [
                    'id' => $valid1,
                ],
            ],
        ];

        $valid1 = Uuid::randomHex();
        $valid2 = Uuid::randomHex();
        yield 'Payload with multiple valid entries' => [
            [
                [$valid1, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM, 'de-DE']],
                [$valid2, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]],
            ],
            [],
            [
                [
                    'id' => $valid1,
                ],
                [
                    'id' => $valid2,
                ],
            ],
        ];

        $invalidId1 = Uuid::randomHex();

        yield 'Payload with single invalid entry' => [
            [
                [$invalidId1, Defaults::LANGUAGE_SYSTEM],
            ],
            [$invalidId1],
        ];

        $invalidId1 = Uuid::randomHex();
        $invalidId2 = Uuid::randomHex();

        yield 'Payload with multiple invalid entries' => [
            [
                [$invalidId1, Defaults::LANGUAGE_SYSTEM],
                [$invalidId2, Defaults::LANGUAGE_SYSTEM],
            ],
            [$invalidId1, $invalidId2],
        ];

        $valid1 = Uuid::randomHex();
        $invalidId1 = Uuid::randomHex();
        $invalidId2 = Uuid::randomHex();

        yield 'Payload with mixed entries' => [
            [
                [$valid1, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM, 'de-DE']],
                [$invalidId1, Defaults::LANGUAGE_SYSTEM, ['de-DE']],
                [$invalidId2, Defaults::LANGUAGE_SYSTEM],
            ],
            [$invalidId1, $invalidId2],
            [
                [
                    'id' => $valid1,
                ],
            ],
        ];
    }

    /**
     * @param list<array{id: string, languageId: string, languages?: list<array{id: string}>}> $updates
     * @param list<string> $invalids
     * @param list<string> $inserts
     */
    #[DataProvider('getUpdateValidationProvider')]
    public function testUpdateValidation(array $updates, array $invalids = [], array $inserts = []): void
    {
        $deLangId = $this->getDeDeLanguageId();
        foreach ($updates as &$update) {
            if ($update['languageId'] === 'de-DE') {
                $update['languageId'] = $deLangId;
            }

            foreach ($update['languages'] ?? [] as $key => $language) {
                if ($language['id'] === 'de-DE') {
                    $update['languages'][$key]['id'] = $deLangId;
                }
            }
        }
        unset($update);

        $exception = null;

        foreach ($inserts as $id) {
            $this->getSalesChannelRepository()->create([
                $this->getSalesChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]),
            ], Context::createDefaultContext());
        }

        try {
            $this->getSalesChannelRepository()->update($updates, Context::createDefaultContext());
        } catch (WriteException $exception) {
            // nth
        }

        if (!$invalids) {
            static::assertNull($exception);

            return;
        }

        static::assertInstanceOf(WriteException::class, $exception);
        $message = $exception->getMessage();

        foreach ($invalids as $invalid) {
            $expectedError = \sprintf(self::UPDATE_VALIDATION_MESSAGE, $invalid);
            static::assertStringContainsString($expectedError, $message);
        }
    }

    public static function getUpdateValidationProvider(): \Generator
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        yield 'Update default language ids because they are in the language list' => [
            [
                [
                    'id' => $id1,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                ],
                [
                    'id' => $id2,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                ],
            ],
            [],
            [
                $id1,
                $id2,
            ],
        ];

        yield 'Cannot update default language ids because they are not in language list' => [
            [
                [
                    'id' => $id1,
                    'languageId' => 'de-DE',
                ],
                [
                    'id' => $id2,
                    'languageId' => 'de-DE',
                ],
            ],
            [$id1, $id2],
            [$id1, $id2],
        ];

        yield 'Update one valid language and throw one exception' => [
            [
                [
                    'id' => $id1,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                ],
                [
                    'id' => $id2,
                    'languageId' => 'de-DE',
                ],
            ],
            [$id2],
            [$id1, $id2],
        ];

        yield 'Update default language id and languages in same time' => [
            [
                [
                    'id' => $id1,
                    'languageId' => 'de-DE',
                    'languages' => [['id' => 'de-DE']],
                ],
            ],
            [],
            [$id1, $id2],
        ];

        yield 'Update default language id and multiple languages in same time' => [
            [
                [
                    'id' => $id1,
                    'languageId' => 'de-DE',
                    'languages' => [
                        ['id' => 'de-DE'],
                        ['id' => Defaults::LANGUAGE_SYSTEM]],
                ],
            ],
            [],
            [$id1, $id2],
        ];
    }

    public function testPreventDeletionOfDefaultLanguageId(): void
    {
        $this->expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation(
                    \sprintf(self::DELETE_VALIDATION_MESSAGE, TestDefaults::SALES_CHANNEL),
                    null,
                    [],
                    '',
                    null,
                    null,
                ),
            ]),
        ));

        try {
            $this->getSalesChannelLanguageRepository()->delete([[
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
            ]], Context::createDefaultContext());
        } catch (WriteException $e) {
            foreach ($e->getExceptions() as $inner) {
                throw $inner;
            }

            throw $e;
        }
    }

    public function testChangingTheDefaultLanguageAndRemovingThePreviousDefaultInOneWrite(): void
    {
        $id = Uuid::randomHex();
        $newDefaultId = $this->getDeDeLanguageId();
        $context = Context::createDefaultContext();

        $this->getSalesChannelRepository()->create([
            $this->getSalesChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM, $newDefaultId]),
        ], $context);

        static::getContainer()->get(SyncService::class)->sync([
            new SyncOperation('write', SalesChannelDefinition::ENTITY_NAME, SyncOperation::ACTION_UPSERT, [
                ['id' => $id, 'languageId' => $newDefaultId],
            ]),
            new SyncOperation('delete', SalesChannelLanguageDefinition::ENTITY_NAME, SyncOperation::ACTION_DELETE, [
                ['salesChannelId' => $id, 'languageId' => Defaults::LANGUAGE_SYSTEM],
            ]),
        ], $context, new SyncBehavior());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('languages');

        $salesChannel = $this->getSalesChannelRepository()->search($criteria, $context)->getEntities()->first();

        static::assertNotNull($salesChannel);
        static::assertSame($newDefaultId, $salesChannel->getLanguageId());
        static::assertNotNull($salesChannel->getLanguages());
        static::assertSame([$newDefaultId], array_values($salesChannel->getLanguages()->getIds()));
    }

    public function testCurrencyValidationFailsWithoutCurrencyEntry(): void
    {
        $id = Uuid::randomHex();
        $data = $this->getSalesChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]);
        $data['currencies'] = [];

        $this->expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation(
                    \sprintf(self::CURRENCY_INSERT_VALIDATION_MESSAGE, $id),
                    null,
                    [],
                    '',
                    null,
                    null,
                ),
            ]),
        ));

        $this->createSalesChannelAndRethrowConstraintViolation($data);
    }

    public function testCurrencyValidationFailsWhenUpdatingDefaultToUnassignedCurrency(): void
    {
        $id = Uuid::randomHex();
        $this->getSalesChannelRepository()->create([
            $this->getSalesChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]),
        ], Context::createDefaultContext());

        $this->expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation(
                    \sprintf(self::CURRENCY_UPDATE_VALIDATION_MESSAGE, $id),
                    null,
                    [],
                    '',
                    null,
                    null,
                ),
            ]),
        ));

        try {
            $this->getSalesChannelRepository()->update([[
                'id' => $id,
                'currencyId' => Uuid::randomHex(),
            ]], Context::createDefaultContext());
        } catch (WriteException $e) {
            $this->rethrowConstraintViolation($e);
        }
    }

    public function testCurrencyValidationPreventsDefaultCurrencyRemoval(): void
    {
        $this->expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation(
                    \sprintf(self::CURRENCY_DELETE_VALIDATION_MESSAGE, TestDefaults::SALES_CHANNEL),
                    null,
                    [],
                    '',
                    null,
                    null,
                ),
            ]),
        ));

        try {
            $this->getSalesChannelCurrencyRepository()->delete([[
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'currencyId' => Defaults::CURRENCY,
            ]], Context::createDefaultContext());
        } catch (WriteException $e) {
            $this->rethrowConstraintViolation($e);
        }
    }

    public function testChangingDefaultCurrencyAndRemovingPreviousDefaultInOneWrite(): void
    {
        $id = Uuid::randomHex();
        $newDefaultId = $this->createCurrency();
        $context = Context::createDefaultContext();

        $data = $this->getSalesChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]);
        $data['currencies'][] = ['id' => $newDefaultId];
        $this->getSalesChannelRepository()->create([$data], $context);

        static::getContainer()->get(SyncService::class)->sync([
            new SyncOperation('write', SalesChannelDefinition::ENTITY_NAME, SyncOperation::ACTION_UPSERT, [
                ['id' => $id, 'currencyId' => $newDefaultId],
            ]),
            new SyncOperation('delete', SalesChannelCurrencyDefinition::ENTITY_NAME, SyncOperation::ACTION_DELETE, [
                ['salesChannelId' => $id, 'currencyId' => Defaults::CURRENCY],
            ]),
        ], $context, new SyncBehavior());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('currencies');

        $salesChannel = $this->getSalesChannelRepository()->search($criteria, $context)->getEntities()->first();

        static::assertNotNull($salesChannel);
        static::assertSame($newDefaultId, $salesChannel->getCurrencyId());
        static::assertNotNull($salesChannel->getCurrencies());
        static::assertSame([$newDefaultId], array_values($salesChannel->getCurrencies()->getIds()));
    }

    public function testDeletingSalesChannelWillNotBeValidated(): void
    {
        $id = Uuid::randomHex();
        $salesChannelData = $this->getSalesChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]);

        $salesChannelRepository = $this->getSalesChannelRepository();

        $context = Context::createDefaultContext();

        $salesChannelRepository->create([$salesChannelData], $context);

        $salesChannelRepository->delete([[
            'id' => $id,
        ]], Context::createDefaultContext());

        $result = $salesChannelRepository->search(new Criteria([$id]), $context)->getEntities();
        static::assertCount(0, $result);
    }

    public function testAgenticCommerceSalesChannelValidationFailsWithoutLanguageEntry(): void
    {
        $id = Uuid::randomHex();
        $data = $this->getSalesChannelData($id, Defaults::LANGUAGE_SYSTEM);
        $data['typeId'] = Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE;

        $this->expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation(
                    \sprintf(self::INSERT_VALIDATION_MESSAGE, $id),
                    null,
                    [],
                    '',
                    null,
                    null,
                ),
            ]),
        ));

        try {
            $this->getSalesChannelRepository()->create([$data], Context::createDefaultContext());
        } catch (WriteException $e) {
            foreach ($e->getExceptions() as $inner) {
                throw $inner;
            }

            throw $e;
        }
    }

    public function testAgenticCommerceSalesChannelValidationSucceedsWithLanguageEntry(): void
    {
        $id = Uuid::randomHex();
        $data = $this->getSalesChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]);
        $data['typeId'] = Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE;

        $this->getSalesChannelRepository()->create([$data], Context::createDefaultContext());

        $count = (int) static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM sales_channel_language WHERE sales_channel_id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertSame(1, $count);
    }

    public function testProductComparisonSalesChannelValidationFailsWithoutLanguageEntry(): void
    {
        $id = Uuid::randomHex();
        $data = $this->getSalesChannelData($id, Defaults::LANGUAGE_SYSTEM);
        $data['typeId'] = Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON;

        $this->expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation(
                    \sprintf(self::INSERT_VALIDATION_MESSAGE, $id),
                    null,
                    [],
                    '',
                    null,
                    null,
                ),
            ]),
        ));

        try {
            $this->getSalesChannelRepository()->create([$data], Context::createDefaultContext());
        } catch (WriteException $e) {
            foreach ($e->getExceptions() as $inner) {
                throw $inner;
            }

            throw $e;
        }
    }

    public function testProductComparisonSalesChannelValidationSucceedsWithLanguageEntry(): void
    {
        $id = Uuid::randomHex();
        $data = $this->getSalesChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]);
        $data['typeId'] = Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON;

        $this->getSalesChannelRepository()->create([$data], Context::createDefaultContext());

        $count = (int) static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM sales_channel_language WHERE sales_channel_id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertSame(1, $count);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function currencyExcludedSalesChannelTypeProvider(): iterable
    {
        yield 'product comparison' => [Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON];
        yield 'agentic commerce' => [Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE];
    }

    #[DataProvider('currencyExcludedSalesChannelTypeProvider')]
    public function testExcludedSalesChannelTypeSucceedsWithoutDefaultCurrencyInCurrencyList(string $typeId): void
    {
        $id = Uuid::randomHex();
        $data = $this->getSalesChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]);
        $data['typeId'] = $typeId;
        $data['currencies'] = [];

        $this->getSalesChannelRepository()->create([$data], Context::createDefaultContext());

        $count = (int) static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM sales_channel_currency WHERE sales_channel_id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertSame(0, $count);
    }

    /**
     * @param list<string> $languages
     *
     * @return array<string, mixed>
     */
    private function getSalesChannelData(string $id, string $languageId, array $languages = []): array
    {
        $default = [
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'languageId' => $languageId,
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

        if (!$languages) {
            $default['languages'] = $languages;

            return $default;
        }

        foreach ($languages as $language) {
            $default['languages'][] = ['id' => $language];
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createSalesChannelAndRethrowConstraintViolation(array $data): void
    {
        try {
            $this->getSalesChannelRepository()->create([$data], Context::createDefaultContext());
        } catch (WriteException $e) {
            $this->rethrowConstraintViolation($e);
        }
    }

    private function rethrowConstraintViolation(WriteException $exception): never
    {
        foreach ($exception->getExceptions() as $inner) {
            throw $inner;
        }

        throw $exception;
    }

    private function createCurrency(): string
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('currency.repository')->create([[
            'id' => $id,
            'name' => 'Test currency ' . $id,
            'factor' => 1.0,
            'symbol' => '$',
            'isoCode' => 'T' . substr($id, 0, 2),
            'decimalPrecision' => 2,
            'shortName' => 'Test currency',
            'itemRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
            'totalRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
        ]], Context::createDefaultContext());

        return $id;
    }

    /**
     * @return EntityRepository<SalesChannelCollection>
     */
    private function getSalesChannelRepository(): EntityRepository
    {
        return static::getContainer()->get('sales_channel.repository');
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function getSalesChannelLanguageRepository(): EntityRepository
    {
        return static::getContainer()->get('sales_channel_language.repository');
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function getSalesChannelCurrencyRepository(): EntityRepository
    {
        return static::getContainer()->get('sales_channel_currency.repository');
    }
}
