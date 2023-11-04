<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Validation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('sales-channel')]
class SalesChannelValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const DELETE_VALIDATION_MESSAGE = 'Cannot delete default language id from language list of the sales channel with id "%s".';
    private const INSERT_VALIDATION_MESSAGE = 'The sales channel with id "%s" does not have a default sales channel language id in the language list.';
    private const UPDATE_VALIDATION_MESSAGE = 'Cannot update default language id because the given id is not in the language list of sales channel with id "%s"';
    private const DUPLICATED_ENTRY_VALIDATION_MESSAGE = 'The sales channel language "%s" for the sales channel "%s" already exists.';

    /**
     * @param array<string, mixed> $inserts
     * @param array<string> $valids
     * @param array<string> $invalids
     *
     * @dataProvider getInsertValidationProvider
     */
    public function testInsertValidation(array $inserts, array $invalids = [], array $valids = []): void
    {
        $exception = null;

        $deDeLanguageId = $this->getDeDeLanguageId();
        foreach ($inserts as &$insert) {
            foreach ($insert[2] ?? [] as $key => $language) {
                if ($language === 'de-DE') {
                    $insert[2][$key] = $deDeLanguageId;
                }
            }

            $insert = $this->getSalesChannelData(...$insert);
        }

        try {
            $this->getSalesChannelRepository()
                ->create($inserts, Context::createDefaultContext());
        } catch (WriteException $exception) {
            // nth
        }

        if (!$invalids) {
            static::assertNull($exception);

            $this->getSalesChannelRepository()->delete([
                $valids,
            ], Context::createDefaultContext());

            return;
        }

        static::assertInstanceOf(WriteException::class, $exception);
        $message = $exception->getMessage();

        foreach ($invalids as $invalid) {
            $expectedError = sprintf(self::INSERT_VALIDATION_MESSAGE, $invalid);
            static::assertStringContainsString($expectedError, $message);
        }

        $this->getSalesChannelRepository()->delete([
            $valids,
        ], Context::createDefaultContext());
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
     * @param array<string, mixed> $updates
     * @param array<string> $invalids
     * @param array<string, mixed> $inserts
     *
     * @dataProvider getUpdateValidationProvider
     */
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

        $exception = null;

        foreach ($inserts as $id) {
            $this->getSalesChannelRepository()->create([
                $this->getSalesChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]),
            ], Context::createDefaultContext());
        }

        try {
            $this->getSalesChannelRepository()
                ->update($updates, Context::createDefaultContext());
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
            $expectedError = sprintf(self::UPDATE_VALIDATION_MESSAGE, $invalid);
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
    }

    public function testPreventDeletionOfDefaultLanguageId(): void
    {
        static::expectException(WriteException::class);
        static::expectExceptionMessage(sprintf(
            self::DELETE_VALIDATION_MESSAGE,
            TestDefaults::SALES_CHANNEL
        ));

        $this->getSalesChannelLanguageRepository()->delete([[
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
        ]], Context::createDefaultContext());
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

        $result = $salesChannelRepository->search(new Criteria([$id]), $context);
        static::assertCount(0, $result);
    }

    public function testInsertSalesChannelLanguageWhichAlreadyExist(): void
    {
        $id = Uuid::randomHex();

        $salesChannelData = $this
            ->getSalesChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]);

        $context = Context::createDefaultContext();

        $this->getSalesChannelRepository()
            ->create([$salesChannelData], $context);

        static::expectException(WriteException::class);
        static::expectExceptionMessage(sprintf(
            self::DUPLICATED_ENTRY_VALIDATION_MESSAGE,
            Defaults::LANGUAGE_SYSTEM,
            $id
        ));

        $this->getSalesChannelLanguageRepository()->create([[
            'salesChannelId' => $id,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
        ]], $context);

        $this->getSalesChannelRepository()->delete([[
            'id' => $id,
        ]], Context::createDefaultContext());
    }

    public function testOnlyStorefrontAndHeadlessSalesChannelsWillBeSupported(): void
    {
        $id = Uuid::randomHex();
        $languageId = Defaults::LANGUAGE_SYSTEM;

        $data = $this->getSalesChannelData($id, $languageId);
        $data['typeId'] = Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON;

        $this->getSalesChannelRepository()
            ->create([$data], Context::createDefaultContext());

        $count = (int) $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM sales_channel_language WHERE sales_channel_id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertSame(0, $count);

        $this->getSalesChannelRepository()->delete([[
            'id' => $id,
        ]], Context::createDefaultContext());
    }

    /**
     * @param array<string> $languages
     *
     * @return array<mixed>
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

    private function getNonDefaultLanguageId(): string
    {
        $nonDefaultLanguageId = $this->getDeDeLanguageId();
        static::assertNotSame(Defaults::LANGUAGE_SYSTEM, $nonDefaultLanguageId);

        return $nonDefaultLanguageId;
    }

    private function getSalesChannelRepository(): EntityRepository
    {
        return $this->getContainer()->get('sales_channel.repository');
    }

    private function getSalesChannelLanguageRepository(): EntityRepository
    {
        return $this->getContainer()->get('sales_channel_language.repository');
    }
}
