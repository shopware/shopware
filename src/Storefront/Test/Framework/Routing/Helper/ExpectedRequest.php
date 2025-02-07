<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Routing\Helper;

/**
 * @internal
 */
class ExpectedRequest
{
    public string $url;

    public ?string $baseUrl = null;

    public ?string $domainId = null;

    public ?string $salesChannelId = null;

    public ?bool $isStorefrontRequest = null;

    public ?string $locale = null;

    public ?string $currency = null;

    public ?string $languageCode = null;

    public ?string $snippetLanguageCode = null;

    /**
     * @var class-string<\Throwable>|null
     */
    public ?string $exception = null;

    public ?string $resolvedUrl = null;

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
