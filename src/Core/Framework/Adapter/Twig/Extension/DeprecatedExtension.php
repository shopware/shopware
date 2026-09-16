<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Adapter\Twig\NodeVisitor\DeprecatedAliasNodeVisitor;
use Shopware\Core\Framework\Adapter\Twig\TokenParser\DeprecatedTokenParser;
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
    public function getTokenParsers(): array
    {
        return [new DeprecatedTokenParser()];
    }

    /**
     * @return NodeVisitorInterface[]
     */
    public function getNodeVisitors(): array
    {
        return [new DeprecatedAliasNodeVisitor()];
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('sw_trigger_deprecation', $this->triggerDeprecationOrThrow(...))];
    }

    public function triggerDeprecationOrThrow(string $removedIn, string $message): void
    {
        Feature::triggerDeprecationOrThrow($removedIn, $message);
    }
}
