<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('core')]
interface RequestContextResolverInterface
{
    public function resolve(Request $request): void;
}
