<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Mapping\Projection\AbstractContentPropertyProjection;
use Shopware\Core\Framework\Log\Package;

/**
 * A string-to-string projection for tests that need a registered one without pulling in a domain entity.
 *
 * Uppercasing is chosen because it makes "the projection ran" visible in an assertion on the delivered value,
 * which a pass-through could not.
 *
 * @final
 */
#[Package('framework')]
class StubUppercaseProjection extends AbstractContentPropertyProjection
{
    final public const NAME = 'stub_uppercase';

    public function name(): string
    {
        return self::NAME;
    }

    public function inputType(): string
    {
        return 'string';
    }

    public function outputType(): string
    {
        return 'string';
    }

    public function project(mixed $value): string
    {
        \assert(\is_string($value));

        return strtoupper($value);
    }
}
