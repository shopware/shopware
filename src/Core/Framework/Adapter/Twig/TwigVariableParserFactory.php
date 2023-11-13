<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Log\Package;
use Twig\Environment;

#[Package('core')]
class TwigVariableParserFactory
{
    public function getParser(Environment $twig): TwigVariableParser
    {
        return new TwigVariableParser($twig);
    }
}
