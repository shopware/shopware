<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @internal
 */
class TestBrowser extends KernelBrowser
{
    public function setServerParameter(string $key, mixed $value): void
    {
        $this->server[$key] = $value;
    }

    /**
     * Custom override because the original Symfony implementation always sets the `HTTP_ACCEPT` header and
     * removes it afterward. We only want to do this if that header wasn't stored before in `setServerParameter`.
     *
     * Shopware often wants to use that accept header with a value of `application/vnd.api+json,application/json`
     * see e.g. \Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour::createClient
     *
     * @param array<int|string, mixed> $parameters
     * @param array<string, string> $server
     */
    public function jsonRequest(string $method, string $uri, array $parameters = [], array $server = [], bool $changeHistory = true): Crawler
    {
        $content = json_encode($parameters, \JSON_PRESERVE_ZERO_FRACTION);
        \assert(\is_string($content));

        $this->setServerParameter('CONTENT_TYPE', 'application/json');

        $acceptAlreadySet = $this->getServerParameter('HTTP_ACCEPT', false);
        if (!$acceptAlreadySet) {
            $this->setServerParameter('HTTP_ACCEPT', 'application/json');
        }

        try {
            return $this->request($method, $uri, [], [], $server, $content, $changeHistory);
        } finally {
            unset($this->server['CONTENT_TYPE']);

            if (!$acceptAlreadySet) {
                unset($this->server['HTTP_ACCEPT']);
            }
        }
    }
}
