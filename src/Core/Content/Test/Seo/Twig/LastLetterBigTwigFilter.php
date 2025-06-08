<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Seo\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @internal
 */
class LastLetterBigTwigFilter extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('lastBigLetter', $this->convert(...)),
        ];
    }

    public function convert(string $text): string
    {
        return strrev(ucfirst(strrev($text)));
    }
}
