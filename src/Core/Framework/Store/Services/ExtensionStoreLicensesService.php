<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Store\Struct\CartPositionStruct;
use Shopware\Core\Framework\Store\Struct\CartStruct;
use Shopware\Core\Framework\Store\Struct\LicenseCollection;
use Shopware\Core\Framework\Store\Struct\ReviewStruct;

/**
 * @internal
 */
class ExtensionStoreLicensesService extends AbstractExtensionStoreLicensesService
{
    private StoreClient $client;

    private LicenseLoader $licenseLoader;

    private ExtensionDownloader $extensionDownloader;

    public function __construct(
        StoreClient $client,
        LicenseLoader $licenseLoader,
        ExtensionDownloader $extensionDownloader
    ) {
        $this->client = $client;
        $this->licenseLoader = $licenseLoader;
        $this->extensionDownloader = $extensionDownloader;
    }

    public function getLicensedExtensions(Context $context): LicenseCollection
    {
        $licenseCollection = new LicenseCollection();

        $licensesResponse = $this->client->getLicenses($context);

        foreach ($licensesResponse['data'] as $license) {
            $licenseCollection->add($this->licenseLoader->loadFromArray($license));
        }

        $licenseCollection->setTotal(\count($licensesResponse['data']));

        return $licenseCollection;
    }

    public function purchaseExtension(int $extensionId, int $variantId, Context $context): void
    {
        $cart = $this->client->createCart($extensionId, $variantId, $context);

        $this->client->orderCart($cart, $context);

        foreach ($this->getExtensionNamesFromCart($cart) as $name) {
            $this->extensionDownloader->download($name, $context);
        }
    }

    public function cancelSubscription(int $licenseId, Context $context): void
    {
        $this->client->cancelSubscription($licenseId, $context);
    }

    public function rateLicensedExtension(ReviewStruct $rating, Context $context): void
    {
        $this->client->createRating($rating, $context);
    }

    /**
     * @codeCoverageIgnore
     */
    protected function getDecorated(): AbstractExtensionStoreLicensesService
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return array<string>
     */
    private function getExtensionNamesFromCart(CartStruct $cart): array
    {
        return array_map(static function (CartPositionStruct $position): string {
            return $position->getExtensionName();
        }, $cart->getPositions()->getElements());
    }
}
