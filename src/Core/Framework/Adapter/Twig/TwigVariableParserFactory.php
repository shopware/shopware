<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Log\Package;
use Twig\Environment;

/**
 * @deprecated tag:v6.8.0 - reason:becomes-internal - Will be internal in v6.8.0
 */
#[Package('framework')]
class TwigVariableParserFactory
{
    public function getParser(Environment $twig): TwigVariableParser
    {
        return new TwigVariableParser($twig);
    }
}
