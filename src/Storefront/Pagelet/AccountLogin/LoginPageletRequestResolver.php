<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountLogin;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class LoginPageletRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === LoginPageletRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $loginPageletRequest = new LoginPageletRequest();

        $event = new LoginPageletRequestEvent($request, $context, $loginPageletRequest);
        $this->eventDispatcher->dispatch(LoginPageletRequestEvent::NAME, $event);

        yield $event->getLoginPageletRequest();
    }
}
