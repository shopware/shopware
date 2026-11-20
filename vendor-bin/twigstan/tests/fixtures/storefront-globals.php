<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use Twig\Environment;

use function PHPStan\Testing\assertType;

function inferStorefrontGlobals(Environment $twig): void
{
    $context = ['categoryId' => 'category'];
    $context += $twig->getGlobals();
    assertType('bool', \array_key_exists('context', $context));
    assertType('Shopware\Core\System\SalesChannel\SalesChannelContext', $context['context']);
    assertType('451', $context['jsonLdFlags']);
    assertType('\'category\'', $context['categoryId']);
    // @phpstan-ignore offsetAccess.notFound (The only include must not inherit ordinary listener parameters.)
    assertType('*ERROR*', $context['listenerParameter']);
    // @phpstan-ignore offsetAccess.notFound (Header render parameters are local inputs, not Twig globals.)
    assertType('*ERROR*', $context['headerParameters']);

    $context = ['context' => 'explicit override'];
    $context += $twig->getGlobals();
    assertType('\'explicit override\'', $context['context']);

    $context = ['context' => null];
    $context += $twig->getGlobals();
    assertType('null', $context['context']);
}

/**
 * @param array{context: 'explicit override', ...<string, mixed>} $context
 */
function inferUnsealedStorefrontGlobals(Environment $twig, array $context): void
{
    $context = array_merge($twig->getGlobals(), $context);
    assertType('\'explicit override\'', $context['context']);
    assertType('mixed', $context['dynamic']);
}

function inferMergedStorefrontGlobals(Environment $twig): void
{
    $context = ['categoryId' => 'category'];
    $context = array_merge($twig->getGlobals(), $context);
    assertType('451', $context['jsonLdFlags']);
    assertType('\'category\'', $context['categoryId']);
}

/**
 * @param array{context?: 'explicit override'} $context
 */
function inferOptionalStorefrontOverride(Environment $twig, array $context): void
{
    $context += $twig->getGlobals();
    assertType('\'explicit override\'|Shopware\Core\System\SalesChannel\SalesChannelContext', $context['context']);
    assertType('bool', \array_key_exists('context', $context));
}
