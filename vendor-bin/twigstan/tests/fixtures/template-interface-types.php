<?php declare(strict_types=1);

namespace TemplateInterfaceFixture;

use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan\TemplateContracts;
use Twig\Environment;

use function PHPStan\Testing\assertType;

function inferInterfaceInputs(Environment $twig): void
{
    $inherited = TemplateContracts::context($twig, ['extra' => 'parent', 'term' => 'parent'], ['term' => 'override'], true, 'template-interface.twig', 'array{term?: string|null}', false);
    assertType('array{extra: \'parent\', term: \'override\'}', $inherited);

    $missing = TemplateContracts::context($twig, ['extra' => 'parent'], [], true, 'template-interface.twig', 'array{term?: string|null}', false);
    assertType('array{extra: \'parent\'}', $missing);

    $only = TemplateContracts::context($twig, ['extra' => 'parent'], ['term' => 'only'], false, 'template-interface.twig', 'array{term?: string|null}', false);
    assertType('array{term: \'only\'}', $only);
}
