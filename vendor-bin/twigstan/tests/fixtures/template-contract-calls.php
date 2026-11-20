<?php declare(strict_types=1);

namespace TemplateContractFixture;

use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan\TemplateContracts;
use Twig\Environment;

function calls(Environment $twig, mixed $unknown): void
{
    $contexts = [TemplateContracts::context($twig, [], ['name' => 'valid', 'extra' => 42], true, 'template-contract.twig', 'array{name: string, size?: string|null}')];
    $contexts[] = TemplateContracts::context($twig, [], ['name' => 42], true, 'template-contract.twig', 'array{name: string, size?: string|null}');
    $contexts[] = TemplateContracts::context($twig, [], [], true, 'template-contract.twig', 'array{name: string, size?: string|null}');
    $contexts[] = TemplateContracts::context($twig, ['name' => 'parent'], [], false, 'template-contract.twig', 'array{name: string, size?: string|null}');
    $contexts[] = TemplateContracts::context($twig, ['name' => 'parent'], [], true, 'template-contract.twig', 'array{name: string, size?: string|null}');
    $contexts[] = TemplateContracts::context($twig, ['name' => 42], ['name' => 'override'], true, 'template-contract.twig', 'array{name: string, size?: string|null}');
    $contexts[] = TemplateContracts::context($twig, [], ['name' => 'valid', 'size' => 42], true, 'template-contract.twig', 'array{name: string, size?: string|null}');
    $contexts[] = TemplateContracts::context($twig, [], ['name' => $unknown, 'size' => null], true, 'template-contract.twig', 'array{name: string, size?: string|null}');
}
