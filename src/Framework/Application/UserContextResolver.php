<?php

class UserContextResolver
{
    /**
     * @var \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage
     */
    private $tokenStorage;

    public function resolve(\Symfony\Component\HttpFoundation\Request $request): ContextParameterBag
    {
        $token = $this->tokenStorage->getToken();

        if ($token instanceof RestUserAuthentification) {
            $defaults = $this->connection->fetch('SELECT * FROM user WHERE id = id');

            $defaults = array_merge(
                $defaults,
                $this->getRuntimeParameters($request)
            );

            $request->attributes->set('context-key', new ShopContext());

            return ;
        }

        throw new RuntimeException('Not authentificated');
    }

    private function getRuntimeParameters(\Symfony\Component\HttpFoundation\Request $request)
    {
        return [];
    }
}


class ApplicationContextResolver
{
    private $session;

    public function resolve(\Symfony\Component\HttpFoundation\Request $request): ContextParameterBag
    {
        $applicationId = $request->headers->get('application-id');
        if (!$applicationId) {
            return $this->decorated->resolve($request);
        }

        $defaults = $this->connection->fetch('SELECT * FROM application WHERE id = id');

        $contextToken = \Ramsey\Uuid\Uuid::uuid4()->getHex();
        if ($request->headers->has('context-token')) {
            $contextToken = $request->headers->get('context-token');

        } else if ($this->session->isStarted()) {
            $contextToken = $this->session->get('context-token', null);

            if (!$contextToken) {
                $contextToken = \Ramsey\Uuid\Uuid::uuid4()->getHex();
                $this->session->set('context-token', $contextToken);
            }
        }

        $runtime = $this->connection->fetch('SELECT * FROM context_paramerter_storage WHERE id = id');

        $defaults = array_merge($defaults, $runtime);

        $request->attributes->set('context-key', new ShopContext());
        $request->attributes->set('storefront-context', new \Shopware\Context\Struct\StorefrontContext());

        return;
        return new ContextParameterBag(
            $defaults['languageId'],
            $defaults['currencyId'],
            (array) $defaults['catalogIds'],
            $defaults
        );
    }
}