<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;

#[Package('framework')]
class RobotsUserAgentBlock
{
    /**
     * @param list<RobotsDirective> $directives
     */
    public function __construct(
        public readonly string $userAgent,
        public readonly array $directives
    ) {
    }

    /**
     * @return list<RobotsDirective>
     */
    public function getPathDirectives(): array
    {
        return array_values(array_filter(
            $this->directives,
            static fn (RobotsDirective $directive) => $directive->isPathBased()
        ));
    }

    /**
     * @return list<RobotsDirective>
     */
    public function getNonPathDirectives(): array
    {
        return array_values(array_filter(
            $this->directives,
            static fn (RobotsDirective $directive) => !$directive->isPathBased()
        ));
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
     * The hash is based on the user-agent and non-path directives only,
     * as path directives vary by domain and should not affect deduplication.
     */
    public function getHash(): string
    {
        return Hasher::hash([
            $this->userAgent,
            array_map(
                static fn (RobotsDirective $d) => [$d->type->value, $d->value],
                $this->getNonPathDirectives()
            ),
        ]);
    }
}
