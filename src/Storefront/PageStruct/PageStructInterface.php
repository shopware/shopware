<?php declare(strict_types=1);

namespace Shopware\Storefront\PageStruct;

use Symfony\Component\HttpFoundation\Request;

interface PageStructInterface
{
    public function fromRequest(Request $request);
}
