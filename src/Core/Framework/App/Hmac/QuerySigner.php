<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Hmac;

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;

/**
 * @internal only for use by the app-system
 */
class QuerySigner
{
    private string $shopUrl;

    private string $shopwareVersion;

    private LocaleProvider $localeProvider;

    private ShopIdProvider $shopIdProvider;

    public function __construct(
        string $shopUrl,
        string $shopwareVersion,
        LocaleProvider $localeProvider,
        ShopIdProvider $shopIdProvider
    ) {
        $this->shopUrl = $shopUrl;
        $this->shopwareVersion = $shopwareVersion;
        $this->localeProvider = $localeProvider;
        $this->shopIdProvider = $shopIdProvider;
    }

    public function signUri(string $uri, string $secret, Context $context): UriInterface
    {
        $uri = Uri::withQueryValues(new Uri($uri), [
            'shop-id' => $this->shopIdProvider->getShopId(),
            'shop-url' => $this->shopUrl,
            'timestamp' => (new \DateTime())->getTimestamp(),
            'sw-version' => $this->shopwareVersion,
            'sw-context-language' => $context->getLanguageId(),
            'sw-user-language' => $this->localeProvider->getLocaleFromContext($context),
        ]);

        return (new RequestSigner())->signUri($uri, $secret);
    }
}
