<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Routing\Helper;

/**
 * @internal
 */
class ExpectedRequest
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    public $url;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    public $baseUrl;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    public $domainId;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    public $salesChannelId;

    /**
     * @var bool|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    public $isStorefrontRequest;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    public $locale;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    public $currency;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    public $languageCode;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    public $snippetLanguageCode;

    /**
     * @var class-string<\Throwable>|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    public $exception;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    public $resolvedUrl;

    /**
     * @param class-string<\Throwable>|null $exception
     */
    public function __construct(
        string $url,
        ?string $baseUrl,
        ?string $resolvedUrl,
        ?string $domainId,
        ?string $salesChannelId,
        ?bool $isStorefrontRequest,
        ?string $locale,
        ?string $currency,
        ?string $languageCode,
        ?string $snippetLanguageCode,
        ?string $exception = null
    ) {
        $this->url = $url;
        $this->domainId = $domainId;
        $this->salesChannelId = $salesChannelId;
        $this->isStorefrontRequest = $isStorefrontRequest;
        $this->locale = $locale;
        $this->currency = $currency;
        $this->languageCode = $languageCode;
        $this->snippetLanguageCode = $snippetLanguageCode;
        $this->baseUrl = $baseUrl;
        $this->exception = $exception;
        $this->resolvedUrl = $resolvedUrl;
    }
}
