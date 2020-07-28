<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Translation;

use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class RequestStackCacheInvalidate extends RequestStack
{
    /**
     * @var RequestStack
     */
    private $decorated;

    /**
     * @var TranslatorRequestCache
     */
    private $translatorRequestCache;

    public function __construct(RequestStack $decorated, TranslatorRequestCache $translatorRequestCache)
    {
        $this->decorated = $decorated;
        $this->translatorRequestCache = $translatorRequestCache;
    }

    public function push(Request $request)
    {
        $this->translatorRequestCache->reset();
        $this->decorated->push($this->setSnippetSetIdByRequest($request));
    }

    public function pop()
    {
        $this->translatorRequestCache->reset();

        return $this->setSnippetSetIdByRequest($this->decorated->pop());
    }

    public function getCurrentRequest()
    {
        return $this->decorated->getCurrentRequest();
    }

    public function getMasterRequest()
    {
        return $this->decorated->getMasterRequest();
    }

    public function getParentRequest()
    {
        return $this->decorated->getParentRequest();
    }

    private function setSnippetSetIdByRequest(?Request $request): ?Request
    {
        if (!$request) {
            $this->translatorRequestCache->setSnippetSetId(null);
            return null;
        }

        $this->translatorRequestCache->setSnippetSetId($request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID));

        return $request;
    }
}
