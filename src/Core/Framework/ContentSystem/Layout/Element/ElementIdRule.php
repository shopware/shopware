<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element;

use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeConstraints;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\Log\Package;

/**
 * The element-id value domain, stated once for the two sites that enforce it — {@see StoredElementCodec} on
 * decode, {@see StoredTreeConstraints} on write. It must stay equal to the published `ContentElementId`
 * pattern, which `ElementIdSchemaConformanceTest` pins against this class.
 *
 * @internal
 */
#[Package('framework')]
final class ElementIdRule
{
    /**
     * ECMA-262's `LineTerminator` set, which is what a JSON Schema `pattern` excludes from `.` — narrower
     * than Unicode's newlines, so NEL, vertical tab and form feed stay admitted.
     */
    private const LINE_TERMINATORS = [
        "\n" => 'U+000A',
        "\r" => 'U+000D',
        "\u{2028}" => 'U+2028',
        "\u{2029}" => 'U+2029',
    ];

    /**
     * Canonical integer literals only. `012` therefore stays admitted, which is what keeps a minted hex id —
     * 32 characters, leading `0` — outside the rule even when every character happens to be a digit.
     */
    private const INTEGER_LITERAL = '/^-?(0|[1-9][0-9]*)\z/';

    /**
     * The predicate each site frames for its own audience (`it …`, `This value …`), or null when admitted.
     *
     * Line terminators are found with `str_contains` rather than a `preg_match` needing the `u` modifier,
     * whose `false` return on malformed UTF-8 would read as "admitted".
     */
    public static function rejection(string $id): ?string
    {
        if ($id === VirtualRootWrapper::VIRTUAL_ROOT_ID) {
            return 'is the reserved virtual-root id';
        }

        if (preg_match(self::INTEGER_LITERAL, $id) === 1) {
            return 'reads as an integer';
        }

        foreach (self::LINE_TERMINATORS as $terminator => $codePoint) {
            if (\str_contains($id, $terminator)) {
                return \sprintf('contains the line terminator %s', $codePoint);
            }
        }

        return null;
    }
}
