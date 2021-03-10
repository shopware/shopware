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
}
