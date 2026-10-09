<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\FuturePropertyVisibilityChangeRule;

function outsideAccess(FutureProtectedProperty $protected): string
{
    return $protected->value . FuturePrivateProperty::$value;
}
