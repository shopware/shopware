<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoUnsafeRequestHasSessionRule;

use Symfony\Component\HttpFoundation\Request;

function missingArgument(Request $request): bool
{
    return $request->hasSession();
}

function explicitFalse(Request $request): bool
{
    return $request->hasSession(false);
}

function dynamicArgument(Request $request, bool $skipIfUninitialized): bool
{
    return $request->hasSession($skipIfUninitialized);
}

function nullableMissingArgument(?Request $request): ?bool
{
    return $request?->hasSession();
}

function explicitTrue(Request $request): bool
{
    return $request->hasSession(true);
}

function namedExplicitTrue(Request $request): bool
{
    return $request->hasSession(skipIfUninitialized: true);
}

function unrelated(object $object): bool
{
    return method_exists($object, 'hasSession');
}
