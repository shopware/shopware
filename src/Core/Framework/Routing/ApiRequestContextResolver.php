<?php declare(strict_types=1);

namespace Shopware\Framework\Routing;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Defaults;
use Shopware\PlatformRequest;
use Shopware\Rest\Firewall\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ApiRequestContextResolver implements RequestContextResolverInterface
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function resolve(Request $master, Request $request): void
    {
        if (!$this->tokenStorage->getToken()) {
            return;
        }

        $user = $this->tokenStorage->getToken()->getUser();
        if (!$user instanceof User) {
            return;
        }

        //sub requests can use context of master
        if ($master->attributes->has(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT)) {
            $request->attributes->set(
                PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT,
                $master->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT)
            );

            return;
        }

        $config = array_replace_recursive(
            json_decode(json_encode($user), true),
            $this->getRuntimeParameters($master)
        );

        $currencyFactory = 1.0;

        $context = new ApplicationContext(
            $master->headers->get(PlatformRequest::HEADER_TENANT_ID),
            Defaults::APPLICATION,
            null,
            [],
            $config['currencyId'],
            $config['languageId'],
            $config['languageId'],
            //$config['fallbackLanguageId'],
            Defaults::LIVE_VERSION,
            $currencyFactory
        );

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
    }

    private function getRuntimeParameters(Request $request): array
    {
        $parameters = [];

        if ($request->headers->has('language')) {
            $parameters['languageId'] = $request->headers->get('language');
        }

        return $parameters;
    }
}
