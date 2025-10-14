<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Util\Hasher;

#[Package('framework')]
class RobotsUserAgentBlock extends Struct
{
    /**
     * @param RobotsDirective[] $directives
     */
    public function __construct(
        public readonly string $userAgent,
        public readonly array $directives
    ) {
    }

    /**
     * @return RobotsDirective[]
     */
    public function getPathDirectives(): array
    {
        return array_filter(
            $this->directives,
            static fn (RobotsDirective $directive) => RobotsDirective::isPathBased($directive->type)
        );
    }

    /**
     * @return RobotsDirective[]
     */
    public function getNonPathDirectives(): array
    {
        return array_filter(
            $this->directives,
            static fn (RobotsDirective $directive) => !RobotsDirective::isPathBased($directive->type)
        );
    }

    public function render(): string
    {
        $output = 'User-agent: ' . $this->userAgent . "\n";
        foreach ($this->directives as $directive) {
            $output .= $directive->render() . "\n";
        }

        return rtrim($output);
    }

    /**
     * Returns a unique hash for this block to enable deduplication.
     */
    public function getHash(): string
    {
        $parts = [$this->userAgent];
        foreach ($this->getNonPathDirectives() as $directive) {
            $parts[] = $directive->type . ':' . $directive->value;
        }

        return Hasher::hash(implode('|', $parts));
    }
}
