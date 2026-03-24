<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Validator;

final class JsonlRowParserFunctionMock
{
    public static bool $forcePregSplitFailure = false;
}

namespace Shopware\Core\Content\ProductExport\Validator;

function preg_split(string $pattern, string $subject, int $limit = -1, int $flags = 0): array|false
{
    if (\Shopware\Tests\Unit\Core\Content\ProductExport\Validator\JsonlRowParserFunctionMock::$forcePregSplitFailure) {
        return false;
    }

    return \preg_split($pattern, $subject, $limit, $flags);
}
