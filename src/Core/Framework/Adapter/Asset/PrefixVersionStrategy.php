<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

#[Package('core')]
class PrefixVersionStrategy implements VersionStrategyInterface
{
    private readonly string $prefix;

    public function __construct(
        string $prefix,
        private readonly VersionStrategyInterface $strategy
    ) {
        $this->prefix = rtrim($prefix, '/');
    }

    public function getVersion(string $path): string
    {
        return $this->applyVersion($path);
    }

    public function applyVersion(string $path): string
    {
        $prefixLength = \strlen($this->prefix);

        if ($path[0] !== '/' && $path !== '\\') {
            ++$prefixLength;
            $path = $this->prefix . '/' . $path;
        } else {
            $path = $this->prefix . $path;
        }

        $appliedPath = $this->strategy->applyVersion($path);

        return substr($appliedPath, $prefixLength);
    }
}
