<?php declare(strict_types=1);

namespace TypeHintCompatibilityFixture;

function compatible(\DateTimeImmutable $date, \DateTime|\DateTimeImmutable $union, ?\DateTimeImmutable $nullable, int $depth): void
{
    $context = ['date' => $date, 'union' => $union, 'nullable' => $nullable, 'depth' => $depth, 'literal' => 0, 'values' => ['count' => 1]];
    twigstan_type_hint($context, 'date', 'DateTimeInterface', false);
    twigstan_type_hint($context, 'union', 'DateTime|DateTimeImmutable', false);
    twigstan_type_hint($context, 'nullable', 'DateTimeInterface|null', false);
    twigstan_type_hint($context, 'depth', 'int', false);
    twigstan_type_hint($context, 'literal', 'int', false);
    twigstan_type_hint($context, 'values', 'array<string, int>', false);
    twigstan_type_hint($context, 'absent', 'string', true);
}

function incompatible(\stdClass $other, \DateTimeImmutable|\stdClass $partial, mixed $unknown, ?\DateTimeImmutable $nullable, int|string $depth): void
{
    $context = ['other' => $other, 'partial' => $partial, 'unknown' => $unknown, 'nullable' => $nullable, 'depth' => $depth];
    twigstan_type_hint($context, 'other', 'DateTime|DateTimeImmutable', false);
    twigstan_type_hint($context, 'partial', 'DateTime|DateTimeImmutable', false);
    twigstan_type_hint($context, 'unknown', 'string', false);
    twigstan_type_hint($context, 'nullable', 'DateTimeInterface', false);
    twigstan_type_hint($context, 'depth', 'int', false);
}

function consecutiveDeclarations(\stdClass $other): void
{
    $context = ['data' => $other];
    twigstan_type_hint($context, 'data', 'DateTime|DateTimeImmutable', false);
    twigstan_type_hint($context, 'data', 'stdClass', false);
}

function requiredMissing(): void
{
    $context = [];
    twigstan_type_hint($context, 'data', 'string', false);
}

/**
 * @param array{data?: string} $context
 */
function requiredPossiblyMissing(array $context): void
{
    twigstan_type_hint($context, 'data', 'string', false);
}

/**
 * @param array{data?: string} $context
 */
function optionalPresentOrMissing(array $context): void
{
    twigstan_type_hint($context, 'data', 'string', true);
}

function refinedMixed(mixed $value): void
{
    $context = ['data' => $value];
    twigstan_type_hint($context, 'data', 'string', false);
    twigstan_type_hint($context, 'data', 'int', false);
}
