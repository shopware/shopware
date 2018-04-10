<?php

namespace Shopware\Framework\Routing;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Defaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ApiRequestContextResolver implements RequestContextResolverInterface
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(TokenStorageInterface $tokenStorage, Connection $connection)
    {
        $this->tokenStorage = $tokenStorage;
        $this->connection = $connection;
    }

    public function resolve(Request $master, Request $request): void
    {
        //todo@jb implement token storage usage, extract auth id of token to fetch context data from user table
        $token = null;
        if (!$token) {
            throw new \RuntimeException('Not authenticated');
        }

        //sub requests can use context of master
        if ($master->attributes->has(self::CONTEXT_REQUEST_ATTRIBUTE)) {
            $request->attributes->set(
                self::CONTEXT_REQUEST_ATTRIBUTE,
                $master->attributes->get(self::CONTEXT_REQUEST_ATTRIBUTE)
            );
            return;
        }

        $defaults = $this->connection->fetchAssoc('SELECT * FROM `user` WHERE id = :id');

        $runtime = $this->getRuntimeParameters($master);

        $config = array_replace_recursive($defaults, $runtime);

        $currencyFactory = 1.0;

        $context = new ApplicationContext(
            null,
            [],
            [],
            $config['currencyId'],
            $config['languageId'],
            $config['fallbackLanguageId'],
            Defaults::LIVE_VERSION,
            $currencyFactory
        );

        $request->attributes->set('context', $context);
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