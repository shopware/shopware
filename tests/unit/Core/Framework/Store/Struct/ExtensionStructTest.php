<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Struct\BinaryCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Store\Struct\FaqCollection;
use Shopware\Core\Framework\Store\Struct\ImageCollection;
use Shopware\Core\Framework\Store\Struct\LicenseStruct;
use Shopware\Core\Framework\Store\Struct\PermissionCollection;
use Shopware\Core\Framework\Store\Struct\StoreCategoryCollection;
use Shopware\Core\Framework\Store\Struct\VariantCollection;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ExtensionStruct::class)]
class ExtensionStructTest extends TestCase
{
    public function testFromArray(): void
    {
        $detailData = $this->getDetailFixture();
        $struct = ExtensionStruct::fromArray($detailData);

        static::assertSame('Tes12SWCloudApp1', $struct->getName());
    }

    /**
     * @param array<string, string> $badValues
     */
    #[DataProvider('badValuesProvider')]
    public function testItThrowsOnMissingData(array $badValues): void
    {
        static::expectException(FrameworkException::class);
        ExtensionStruct::fromArray($badValues);
    }

    public function testItCategorizesThePermissionCollectionWhenStructIsSerialized(): void
    {
        $detailData = $this->getDetailFixture();
        $detailData['permissions'] = new PermissionCollection($detailData['permissions']);

        $extension = ExtensionStruct::fromArray($detailData);

        static::assertInstanceOf(PermissionCollection::class, $extension->getPermissions());

        $serializedExtension = json_decode(json_encode($extension, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        $categorizedPermissions = $serializedExtension['permissions'];

        static::assertCount(3, $categorizedPermissions);
        static::assertSame([
            'product',
            'promotion',
            'other',
        ], array_keys($categorizedPermissions));
    }

    /**
     * @return iterable<list<array<string, string>>>
     */
    public static function badValuesProvider(): iterable
    {
        yield [[]];
        yield [['name' => 'foo']];
        yield [['type' => 'foo']];
        yield [['name' => 'foo', 'label' => 'bar']];
        yield [['label' => 'bar', 'type' => 'foobar']];
    }

    public function testScalarAccessorsRoundTrip(): void
    {
        $extension = new ExtensionStruct();

        $extension->setId(7);
        $extension->setLocalId('local-id');
        $extension->setName('name');
        $extension->setLabel('label');
        $extension->setDescription('description');
        $extension->setShortDescription('short-description');
        $extension->setProducerName('producer-name');
        $extension->setLicense('license');
        $extension->setVersion('version');
        $extension->setLatestVersion('latest-version');
        $extension->setPrivacyPolicyLink('privacy-policy-link');
        $extension->setLanguages(['languages' => 'languages']);
        $extension->setRating(7.5);
        $extension->setNumberOfRatings(7);
        $extension->setIcon('icon');
        $extension->setIconRaw('icon-raw');
        $extension->setActive(true);
        $extension->setType('type');
        $extension->setConfigurable(true);
        $extension->setPrivacyPolicyExtension('privacy-policy-extension');
        $extension->setNotices(['notices' => 'notices']);
        $extension->setSource('source');
        $extension->setUpdateSource('update-source');
        $extension->setAllowDisable(true);
        $extension->setAllowUpdate(true);
        $extension->setDomains(['domains' => 'domains']);
        $extension->setLastUpdateDate('last-update-date');
        $extension->setProducerWebsite('producer-website');
        $extension->setStoreUrl('store-url');
        $extension->setInAppFeaturesAvailable(true);
        $extension->setInAppPurchases(['iap-1']);

        static::assertSame(7, $extension->getId());
        static::assertSame('local-id', $extension->getLocalId());
        static::assertSame('name', $extension->getName());
        static::assertSame('label', $extension->getLabel());
        static::assertSame('description', $extension->getDescription());
        static::assertSame('short-description', $extension->getShortDescription());
        static::assertSame('producer-name', $extension->getProducerName());
        static::assertSame('license', $extension->getLicense());
        static::assertSame('version', $extension->getVersion());
        static::assertSame('latest-version', $extension->getLatestVersion());
        static::assertSame('privacy-policy-link', $extension->getPrivacyPolicyLink());
        static::assertSame(['languages' => 'languages'], $extension->getLanguages());
        static::assertSame(7.5, $extension->getRating());
        static::assertSame(7, $extension->getNumberOfRatings());
        static::assertSame('icon', $extension->getIcon());
        static::assertSame('icon-raw', $extension->getIconRaw());
        static::assertTrue($extension->getActive());
        static::assertSame('type', $extension->getType());
        static::assertTrue($extension->isConfigurable());
        static::assertSame('privacy-policy-extension', $extension->getPrivacyPolicyExtension());
        static::assertSame(['notices' => 'notices'], $extension->getNotices());
        static::assertSame('source', $extension->getSource());
        static::assertSame('update-source', $extension->getUpdateSource());
        static::assertTrue($extension->isAllowDisable());
        static::assertTrue($extension->isAllowUpdate());
        static::assertSame(['domains' => 'domains'], $extension->getDomains());
        static::assertSame('last-update-date', $extension->getLastUpdateDate());
        static::assertSame('producer-website', $extension->getProducerWebsite());
        static::assertSame('store-url', $extension->getStoreUrl());
        static::assertTrue($extension->isInAppFeaturesAvailable());
        static::assertSame(['iap-1'], $extension->getInAppPurchases());
    }

    public function testAssociationAccessorsRoundTrip(): void
    {
        $extension = new ExtensionStruct();

        $variants = new VariantCollection();
        $faq = new FaqCollection();
        $binaries = new BinaryCollection();
        $images = new ImageCollection();
        $categories = new StoreCategoryCollection();
        $permissions = new PermissionCollection();
        $storeLicense = new LicenseStruct();
        $installedAt = new \DateTimeImmutable('2026-01-01 00:00:00');
        $storeExtension = new ExtensionStruct();
        $updatedAt = new \DateTimeImmutable('2026-01-01 00:00:00');

        $extension->setVariants($variants);
        $extension->setFaq($faq);
        $extension->setBinaries($binaries);
        $extension->setImages($images);
        $extension->setCategories($categories);
        $extension->setPermissions($permissions);
        $extension->setStoreLicense($storeLicense);
        $extension->setInstalledAt($installedAt);
        $extension->setStoreExtension($storeExtension);
        $extension->setUpdatedAt($updatedAt);

        static::assertSame($variants, $extension->getVariants());
        static::assertSame($faq, $extension->getFaq());
        static::assertSame($binaries, $extension->getBinaries());
        static::assertSame($images, $extension->getImages());
        static::assertSame($categories, $extension->getCategories());
        static::assertSame($permissions, $extension->getPermissions());
        static::assertSame($storeLicense, $extension->getStoreLicense());
        static::assertSame($installedAt, $extension->getInstalledAt());
        static::assertSame($storeExtension, $extension->getStoreExtension());
        static::assertSame($updatedAt, $extension->getUpdatedAt());
    }

    /**
     * @return array<string, mixed>
     */
    private function getDetailFixture(): array
    {
        $content = file_get_contents(__DIR__ . '/../_fixtures/responses/extension-detail.json');
        static::assertIsString($content);

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
