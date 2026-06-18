<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends \IteratorAggregate<int, string>
 */
#[Package('framework')]
interface TemplatePathIteratorInterface extends \IteratorAggregate
{
    /**
     * @return iterable<string>
     */
    public function getTemplatePathsForSubPath(string $subPath, bool $includeDotFiles = false): iterable;
}
