<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountRegistration;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AccountRegistrationPageletRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === AccountRegistrationPageletRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $registrationPageletRequest = new AccountRegistrationPageletRequest();

        $event = new AccountRegistrationPageletRequestEvent($request, $context, $registrationPageletRequest);
        $this->eventDispatcher->dispatch(AccountRegistrationPageletRequestEvent::NAME, $event);

        yield $event->getAccountRegistrationPageletRequest();
    }
}
