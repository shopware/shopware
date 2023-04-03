<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

#[Package('core')]
class PcreExtension extends AbstractExtension
{
    /**
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('preg_replace', $this->pregReplace(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('preg_match', $this->pregMatch(...)),
        ];
    }

    /**
     * @return string|string[]
     */
    public function pregReplace(string $subject, string $pattern, string $replacement): string|array
    {
        $value = preg_replace($pattern, $replacement, $subject);

        if ($value === null) {
            throw new \RuntimeException(sprintf('Error while processing Twig preg_replace filter. Error: %s', preg_last_error_msg()));
        }

        return $value;
    }

    public function pregMatch(string $subject, string $pattern): bool
    {
        $result = preg_match($pattern, $subject);

        if ($result === false) {
            throw new \RuntimeException(sprintf('Error while processing Twig preg_match function. Error: %s', preg_last_error_msg()));
        }

        return (bool) $result;
    }
}
