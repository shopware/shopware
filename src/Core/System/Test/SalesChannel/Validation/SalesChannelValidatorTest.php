<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Validation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class SalesChannelValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCreateSalesChannelWithoutDefaultLanguageId(): void
    {
        $salesChannelId = Uuid::randomHex();
        $nonDefaultLanguageId = $this->getNonDefaultLanguageId();
        $languages = [['id' => $nonDefaultLanguageId]];

        $salesChannelData = $this
            ->getSingleSalesChannelData($salesChannelId, $languages);

        static::expectException(WriteException::class);
        static::expectExceptionMessage(sprintf(
            'SalesChannel with id "%s" has no default language id set.',
            $salesChannelId
        ));

        $this->getSalesChannelRepository()->create($salesChannelData, Context::createDefaultContext());
    }

    public function testCreateSalesChannelWithDefaultLanguageId(): void
    {
        $salesChannelId = Uuid::randomHex();
        $nonDefaultLanguageId = $this->getNonDefaultLanguageId();

        $context = Context::createDefaultContext();
        $repository = $this->getSalesChannelRepository();

        $languages = [
            ['id' => Defaults::LANGUAGE_SYSTEM],
            ['id' => $nonDefaultLanguageId],
        ];

        $salesChannelData = $this
            ->getSingleSalesChannelData($salesChannelId, $languages);

        $repository->create($salesChannelData, $context);

        $criteria = $this->getSalesChannelCriteria($salesChannelId);

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $repository->search($criteria, $context)->first();

        static::assertTrue($salesChannel->getLanguages()->has(Defaults::LANGUAGE_SYSTEM));
    }

    public function testCreateMultipleSalesChannelsOneHasNoDefaultLanguageId(): void
    {
        $salesChannelIdWithoutDefaultLanguageId = Uuid::randomHex();
        $nonDefaultLanguageId = $this->getNonDefaultLanguageId();

        $languageIds = [
            Uuid::randomHex() => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
                ['id' => $nonDefaultLanguageId],
            ],
            Uuid::randomHex() => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
            ],
            $salesChannelIdWithoutDefaultLanguageId => [
                ['id' => $nonDefaultLanguageId],
            ],
        ];

        $multipleSalesChannelData = $this->getMultipleSalesChannelData($languageIds);
        static::expectException(WriteException::class);
        static::expectExceptionMessage(sprintf(
            'SalesChannel with id "%s" has no default language id set.',
            $salesChannelIdWithoutDefaultLanguageId
        ));

        $this->getSalesChannelRepository()->create($multipleSalesChannelData, Context::createDefaultContext());
    }

    public function testCreateMultipleSalesChannelsMultipleHaveNoDefaultLanguageId(): void
    {
        $salesChannelIdWithoutDefaultLanguageId1 = Uuid::randomHex();
        $salesChannelIdWithoutDefaultLanguageId2 = Uuid::randomHex();
        $nonDefaultLanguageId = $this->getNonDefaultLanguageId();

        $languageIds = [
            $salesChannelIdWithoutDefaultLanguageId1 => [
                ['id' => $nonDefaultLanguageId],
            ],
            Uuid::randomHex() => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
            ],
            $salesChannelIdWithoutDefaultLanguageId2 => [
                ['id' => $nonDefaultLanguageId],
            ],
        ];

        $multipleSalesChannelData = $this->getMultipleSalesChannelData($languageIds);
        static::expectException(WriteException::class);
        static::expectExceptionMessage(sprintf(
            'SalesChannel with id "%s" has no default language id set.',
            $salesChannelIdWithoutDefaultLanguageId1
        ));
        static::expectExceptionMessage(sprintf(
            'SalesChannel with id "%s" has no default language id set.',
            $salesChannelIdWithoutDefaultLanguageId2
        ));

        $this->getSalesChannelRepository()
            ->create($multipleSalesChannelData, Context::createDefaultContext());
    }

    public function testPreventDeletionOfDefaultLanguageId(): void
    {
        static::expectException(WriteException::class);
        static::expectExceptionMessage(sprintf(
            'Cannot delete default language id for SalesChannel with id "%s".',
            Defaults::SALES_CHANNEL
        ));

        $this->getSalesChannelLanguageRepository()->delete([[
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
        ]], Context::createDefaultContext());
    }

    public function testUpdateSalesChannelWithNoDefaultLanguageIdBecauseIsAlreadyIn(): void
    {
        $updateData = [[
            'id' => Defaults::SALES_CHANNEL,
            'languages' => [['id' => $this->getNonDefaultLanguageId()]],
        ]];

        $context = Context::createDefaultContext();
        $repository = $this->getSalesChannelRepository();

        $repository->update($updateData, $context);

        $criteria = $this->getSalesChannelCriteria(Defaults::SALES_CHANNEL);

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $repository->search($criteria, $context)->first();
        $languages = $salesChannel->getLanguages()->getElements();

        static::assertArrayHasKey(Defaults::LANGUAGE_SYSTEM, $languages);
    }

    public function testOtherOperationsDoNothing(): void
    {
        $repository = $this->getSalesChannelRepository();
        $context = Context::createDefaultContext();

        $nonDefaultLanguageId = $this->getNonDefaultLanguageId();
        $salesChannelData = [[
            'id' => Defaults::SALES_CHANNEL,
            'languages' => [['id' => $nonDefaultLanguageId]],
        ]];

        $repository->update($salesChannelData, $context);

        $criteria = $this->getSalesChannelCriteria(Defaults::SALES_CHANNEL);
        $salesChannel = $repository->search($criteria, $context)->first();
        $languages = $salesChannel->getLanguages();

        static::assertSame(2, $languages->count());

        $this->getSalesChannelLanguageRepository()->delete([[
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'languageId' => $nonDefaultLanguageId,
        ]], $context);

        $salesChannel = $repository->search($criteria, $context)->first();
        $languages = $salesChannel->getLanguages();

        static::assertSame(1, $languages->count());
    }

    private function getSingleSalesChannelData(string $salesChannelId, array $languages): array
    {
        return [$this->getSalesChannelBaseData($salesChannelId, $languages)];
    }

    private function getMultipleSalesChannelData(array $languageIds): array
    {
        $multipleSalesChannelData = [];
        foreach ($languageIds as $salesChannelId => $language) {
            $multipleSalesChannelData[] = $this->getSalesChannelBaseData($salesChannelId, $language);
        }

        return $multipleSalesChannelData;
    }

    private function getSalesChannelBaseData(
        string $salesChannelId,
        array $languages
    ): array {
        return [
            'id' => $salesChannelId,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
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
            'languages' => $languages,
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'name' => 'first sales-channel',
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
        ];
    }

    private function getSalesChannelCriteria(string $id): Criteria
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('languages');

        return $criteria;
    }

    private function getNonDefaultLanguageId(): string
    {
        $nonDefaultLanguageId = $this->getDeDeLanguageId();
        static::assertNotSame(Defaults::LANGUAGE_SYSTEM, $nonDefaultLanguageId);

        return $nonDefaultLanguageId;
    }

    private function getSalesChannelRepository(): EntityRepositoryInterface
    {
        return $this->getContainer()->get('sales_channel.repository');
    }

    private function getSalesChannelLanguageRepository(): EntityRepositoryInterface
    {
        return $this->getContainer()->get('sales_channel_language.repository');
    }
}
