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
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @package sales-channel
 *
 * @internal
 */
class SalesChannelValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public const DELETE_VALIDATION_MESSAGE = 'Cannot delete default language id from language list of the sales channel with id "%s".';
    public const INSERT_VALIDATION_MESSAGE = 'The sales channel with id "%s" does not have a default sales channel language id in the language list.';
    public const UPDATE_VALIDATION_MESSAGE = 'Cannot update default language id because the given id is not in the language list of sales channel with id "%s"';
    public const DUPLICATED_ENTRY_VALIDATION_MESSAGE = 'The sales channel language "%s" for the sales channel "%s" already exists.';

    /**
     * @dataProvider getInsertValidationProvider
     */
    public function testInsertValidation(array $inserts, array $invalids = [], array $valids = []): void
    {
        $exception = null;

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

    public function getInsertValidationProvider(): \Generator
    {
        $nonDefaultLanguageId = $this->getNonDefaultLanguageId();

        $valid1 = Uuid::randomHex();

        yield 'Payload with single valid entry' => [
            [
                $this->getSalesChannelData($valid1, Defaults::LANGUAGE_SYSTEM, [$nonDefaultLanguageId, Defaults::LANGUAGE_SYSTEM]),
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
                $this->getSalesChannelData($valid1, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM, $nonDefaultLanguageId]),
                $this->getSalesChannelData($valid2, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]),
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
                $this->getSalesChannelData($invalidId1, Defaults::LANGUAGE_SYSTEM),
            ],
            [$invalidId1],
        ];

        $invalidId1 = Uuid::randomHex();
        $invalidId2 = Uuid::randomHex();

        yield 'Payload with multiple invalid entries' => [
            [
                $this->getSalesChannelData($invalidId1, Defaults::LANGUAGE_SYSTEM),
                $this->getSalesChannelData($invalidId2, Defaults::LANGUAGE_SYSTEM),
            ],
            [$invalidId1, $invalidId2],
        ];

        $valid1 = Uuid::randomHex();
        $invalidId1 = Uuid::randomHex();
        $invalidId2 = Uuid::randomHex();

        yield 'Payload with mixed entries' => [
            [
                $this->getSalesChannelData($valid1, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM, $nonDefaultLanguageId]),
                $this->getSalesChannelData($invalidId1, Defaults::LANGUAGE_SYSTEM, [$nonDefaultLanguageId]),
                $this->getSalesChannelData($invalidId2, Defaults::LANGUAGE_SYSTEM),
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
     * @dataProvider getUpdateValidationProvider
     */
    public function testUpdateValidation(array $updates, array $invalids = [], array $inserts = []): void
    {
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

    public function getUpdateValidationProvider(): \Generator
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $nonDefaultLanguageId = $this->getNonDefaultLanguageId();

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
                    'languageId' => $nonDefaultLanguageId,
                ],
                [
                    'id' => $id2,
                    'languageId' => $nonDefaultLanguageId,
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
                    'languageId' => $nonDefaultLanguageId,
                ],
            ],
            [$id2],
            [$id1, $id2],
        ];

        yield 'Update default language id and languages in same time' => [
            [
                [
                    'id' => $id1,
                    'languageId' => $nonDefaultLanguageId,
                    'languages' => [['id' => $nonDefaultLanguageId]],
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
        static::assertSame(0, $result->count());
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
