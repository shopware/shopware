<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Store\Exception\ExtensionNotFoundException;
use Shopware\Core\Framework\Store\Exception\LicenseNotFoundException;
use Shopware\Core\Framework\Store\Struct\CartPositionStruct;
use Shopware\Core\Framework\Store\Struct\CartStruct;
use Shopware\Core\Framework\Store\Struct\LicenseCollection;
use Shopware\Core\Framework\Store\Struct\LicenseStruct;
use Shopware\Core\Framework\Store\Struct\ReviewStruct;

/**
 * @internal
 */
class ExtensionStoreLicensesService extends AbstractExtensionStoreLicensesService
{
    /**
     * @var StoreClient
     */
    private $client;

    /**
     * @var AbstractStoreAppLifecycleService
     */
    private $storeAppLifecycleService;

    /**
     * @var AbstractExtensionDataProvider
     */
    private $extensionDataProvider;

    /**
     * @var LicenseLoader
     */
    private $licenseLoader;

    /**
     * @var ExtensionDownloader
     */
    private $extensionDownloader;

    public function __construct(
        StoreClient $client,
        AbstractStoreAppLifecycleService $storeAppLifecycleService,
        AbstractExtensionDataProvider $extensionDataProvider,
        LicenseLoader $licenseLoader,
        ExtensionDownloader $extensionDownloader
    ) {
        $this->client = $client;
        $this->storeAppLifecycleService = $storeAppLifecycleService;
        $this->licenseLoader = $licenseLoader;
        $this->extensionDataProvider = $extensionDataProvider;
        $this->extensionDownloader = $extensionDownloader;
    }

    public function getLicensedExtensions(Context $context): LicenseCollection
    {
        $licenseCollection = new LicenseCollection();

        $licensesResponse = $this->client->getLicenses($context);

        foreach ($licensesResponse['data'] as $license) {
            $licenseCollection->add($this->licenseLoader->loadFromArray($license));
        }

        $licenseCollection->setTotal((int) \count($licensesResponse['data']));

        return $licenseCollection;
    }

    public function purchaseExtension(int $extensionId, int $variantId, Context $context): void
    {
        $cart = $this->client->createCart($extensionId, $variantId, $context);

        $this->client->orderCart($cart, $context);

        $extensionNames = $this->getExtensionNamesFromCart($cart);

        foreach ($extensionNames as $name) {
            $this->extensionDownloader->download($name, $context);
        }
    }

    public function cancelSubscription(int $licenseId, Context $context): LicenseCollection
    {
        $licenses = $this->getLicensedExtensions($context);
        $license = $this->findLicenseInLicenses($licenseId, $licenses);

        try {
            $extension = $this->extensionDataProvider->getAppEntityFromTechnicalName(
                $license->getLicensedExtension()->getName(),
                $context
            );

            $this->storeAppLifecycleService->removeExtensionAndCancelSubscription(
                $licenseId,
                $license->getLicensedExtension()->getName(),
                $extension->getId(),
                $context
            );
        } catch (ExtensionNotFoundException $e) {
            // extension is not installed. cancel subscription only
            $this->client->cancelSubscription($license->getId(), $context);
        }

        return $this->getLicensedExtensions($context);
    }

    public function rateLicensedExtension(ReviewStruct $rating, Context $context): void
    {
        $this->client->createRating($rating, $context);
    }

    protected function getDecorated(): AbstractExtensionStoreLicensesService
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @var array<string, array<array<string, string>>>
     *
     * @return array<string>
     */
    private function getExtensionNamesFromCart(CartStruct $cart): array
    {
        return array_map(static function (CartPositionStruct $position): string {
            return $position->getExtensionName();
        }, $cart->getPositions()->getElements());
    }

    private function findLicenseInLicenses(int $licenseId, LicenseCollection $licenses): LicenseStruct
    {
        /** @var LicenseStruct $license */
        foreach ($licenses->getElements() as $license) {
            if ($license->getId() === $licenseId) {
                return $license;
            }
        }

        throw new LicenseNotFoundException($licenseId);
    }
}
