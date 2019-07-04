<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use Shopware\Core\Framework\Update\Checkers\CheckerInterface;
use Shopware\Core\Framework\Update\Struct\Version;

class RequirementsValidator
{
    /**
     * @var CheckerInterface[]
     */
    private $checkers;

    public function __construct(iterable $checkers)
    {
        $this->checkers = $checkers;
    }

    public function validate(Version $version): array
    {
        $results = [];

        foreach ($version->checks as $check) {
            foreach ($this->checkers as $checker) {
                if ($checker->supports($check['type'])) {
                    $results[] = $checker->check($check['value']);
                }
            }
        }

        return $results;
    }
}
