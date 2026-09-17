<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Adapter\Twig\NodeVisitor\DeprecatedAliasNodeVisitor;
use Shopware\Core\Framework\Adapter\Twig\Runtime\DeprecatedAlias;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\TwigFunction;

/**
 * @internal
 */
#[Package('framework')]
final class DeprecatedExtension extends AbstractExtension
{
    /**
     * @return NodeVisitorInterface[]
     */
    public function getNodeVisitors(): array
    {
        return [new DeprecatedAliasNodeVisitor()];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('deprecatedAlias', $this->deprecatedAlias(...)),
            new TwigFunction('sw_trigger_deprecation', $this->triggerDeprecationOrThrow(...)),
        ];
    }

    public function deprecatedAlias(mixed $value): DeprecatedAlias
    {
        return new DeprecatedAlias($value);
    }

    public function triggerDeprecationOrThrow(string $removedIn, string $message): void
    {
        Feature::triggerDeprecationOrThrow($removedIn, $message);
    }
}
