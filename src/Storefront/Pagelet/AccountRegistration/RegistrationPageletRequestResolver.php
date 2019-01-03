<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountRegistration;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class RegistrationPageletRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === RegistrationPageletRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $registrationPageletRequest = new RegistrationPageletRequest();

        $event = new RegistrationPageletRequestEvent($request, $context, $registrationPageletRequest);
        $this->eventDispatcher->dispatch(RegistrationPageletRequestEvent::NAME, $event);

        yield $event->getRegistrationPageletRequest();
    }
}
