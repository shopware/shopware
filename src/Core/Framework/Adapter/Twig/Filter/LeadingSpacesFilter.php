<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Filter;

use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @internal
 */
#[Package('framework')]
class LeadingSpacesFilter extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'remove_leading_spaces',
                fn (string $content): string => $this->removeLeadingSpaces($content),
                ['is_safe' => ['all']],
            ),
        ];
    }

    public function removeLeadingSpaces(string $content): string
    {
        $contentStripped = preg_replace('/^[ \t]+/m', '', $content);

        if ($contentStripped !== null) {
            return trim($contentStripped);
        }

        return $content;
    }
}
