<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\Address\AddressEditorModalStruct;

use function PHPStan\Testing\assertType;

function inferStructVars(StructVarsFixture $struct, AddressEditorModalStruct $modal, StructVarsOverride $override): void
{
    $data = $struct->getVars();
    assertType('bool', $data['enabled']);
    assertType('array<string, int>', $data['values']);
    assertType('mixed', $data['dynamic'] ?? null);
    assertType('mixed', $data['hidden'] ?? null);
    assertType('string', $data['pending'] ?? 'fallback');
    assertType('array{custom: int}', $override->getVars());
    assertType('array{known: int}', (new ArrayStruct(['known' => 42]))->getVars());

    $parameters = array_merge(['postedAddress' => 'posted'], $modal->getVars());
    assertType('bool', $parameters['changeShipping']);
    assertType('Shopware\Storefront\Page\Address\Listing\AddressListingPage|null', $parameters['page']);

    $redirect = array_merge($modal->getVars(), ['redirectTo' => 'route']);
    assertType('bool', $redirect['changeShipping']);
    assertType('Shopware\Storefront\Page\Address\Listing\AddressListingPage|null', $redirect['page']);
    assertType('mixed', $redirect['dynamic'] ?? null);
    assertType('\'route\'', $redirect['redirectTo']);
}
