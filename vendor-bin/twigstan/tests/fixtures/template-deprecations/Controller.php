<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan\Fixtures\TemplateDeprecations;

use Shopware\Core\Framework\Log\Package;
use Twig\Environment;

/**
 * @internal
 */
#[Package('framework')]
class Controller
{
    public function show(Environment $twig): string
    {
        return $twig->render('child.twig');
    }
}
