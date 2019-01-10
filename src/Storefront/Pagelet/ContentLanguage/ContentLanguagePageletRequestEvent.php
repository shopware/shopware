<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentLanguage;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class ContentLanguagePageletRequestEvent extends NestedEvent
{
    public const NAME = 'content-language.pagelet.request.event';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CheckoutContext
     */
    private $context;

    /**
     * @var ContentLanguagePageletRequest
     */
    private $contentLanguagePageletRequest;

    public function __construct(
        Request $request, CheckoutContext $context, ContentLanguagePageletRequest $languagePageletRequest
    ) {
        $this->request = $request;
        $this->context = $context;
        $this->contentLanguagePageletRequest = $languagePageletRequest;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getCheckoutContext(): CheckoutContext
    {
        return $this->context;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getContentLanguagePageletRequest(): ContentLanguagePageletRequest
    {
        return $this->contentLanguagePageletRequest;
    }
}
