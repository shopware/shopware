<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Language;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class LanguagePageletRequestEvent extends NestedEvent
{
    public const NAME = 'content.language.pagelet.request.event';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CheckoutContext
     */
    private $context;

    /**
     * @var LanguagePageletRequest
     */
    private $languagePageRequest;

    public function __construct(Request $request, CheckoutContext $context, LanguagePageletRequest $languagePageRequest)
    {
        $this->request = $request;
        $this->context = $context;
        $this->languagePageRequest = $languagePageRequest;
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

    public function getIndexPageRequest(): LanguagePageletRequest
    {
        return $this->languagePageRequest;
    }
}
