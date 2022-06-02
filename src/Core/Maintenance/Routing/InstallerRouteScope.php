<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Routing;

use Shopware\Core\Framework\Routing\AbstractRouteScope;
use Symfony\Component\HttpFoundation\Request;

class InstallerRouteScope extends AbstractRouteScope
{
    public const ID = 'installer';

    /**
     * @var string[]
     */
    protected $allowedPaths = [];

    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function isAllowed(Request $request): bool
    {
        $lockFile = $this->projectDir . '/install.lock';

        return !\is_file($lockFile);
    }

    public function getId(): string
    {
        return self::ID;
    }
}
