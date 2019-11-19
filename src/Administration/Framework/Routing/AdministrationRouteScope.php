<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\Routing;

use Shopware\Core\Framework\Routing\AbstractRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AdministrationRouteScope extends AbstractRouteScope
{
    /**
     * @var string[]
     */
    protected $allowedPaths = ['api'];

    /**
     * @var UrlGeneratorInterface
     */
    private $generator;

    public function __construct(UrlGeneratorInterface $generator)
    {
        $this->generator = $generator;
    }

    public function isAllowedPath(string $path): bool
    {
        $adminPath = $this->generator->generate('administration.index', [], UrlGeneratorInterface::ABSOLUTE_PATH);

        if ($this->generator->getContext()->getBaseUrl() . $path === $adminPath) {
            return true;
        }

        return parent::isAllowedPath($path);
    }

    public function isAllowed(Request $request): bool
    {
        return true;
    }

    public function getId(): string
    {
        return 'administration';
    }
}
