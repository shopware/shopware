<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RawUrlFunctionExtension extends AbstractExtension
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var RequestStack
     */
    private $stack;

    public function __construct(RouterInterface $router, RequestStack $stack)
    {
        $this->router = $router;
        $this->stack = $stack;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('rawUrl', [$this, 'rawUrl']),
        ];
    }

    public function rawUrl(string $name, array $parameters = [], ?string $domain = null): string
    {
        $request = $this->stack->getMainRequest();
        if (!$request) {
            $url = $this->router->generate($name, $parameters);

            return $this->addDomain($url, $domain);
        }

        $attribute = $request->attributes->get('sw-sales-channel-base-url');
        $request->attributes->set('sw-sales-channel-base-url', '');

        $url = $this->router->generate($name, $parameters);

        $request->attributes->set('sw-sales-channel-base-url', $attribute);

        return $this->addDomain($url, $domain);
    }

    private function addDomain(string $url, ?string $domain): string
    {
        if (!$domain) {
            return $url;
        }

        return rtrim($domain, '/') . '/' . ltrim($url, '/');
    }
}
