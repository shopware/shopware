<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element;

use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeConstraints;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndexFactory;
use Shopware\Core\Framework\Log\Package;

/**
 * The element-id value domain, stated once for the two sites that enforce it — {@see StoredElementCodec} on
 * decode and {@see StoredTreeConstraints} on write — and for the `ContentElementId` OpenAPI component that
 * publishes it. A third statement in a third dialect is how the published schemas came to claim
 * `^[0-9a-f]{32}$` while decode admitted `el-1`.
 *
 * Three values are excluded, for three unrelated reasons:
 *
 * - {@see VirtualRootWrapper::VIRTUAL_ROOT_ID} is minted by the wrap step, so an authored element carrying it
 *   collides on every wrapping render.
 * - An id PHP casts to an integer array key puts an integer into {@see ResolvedValueIndexFactory}'s
 *   string-keyed assignments map, which then encodes as a JSON list once those keys run 0..n-1, and as a map
 *   with integer-looking members otherwise.
 * - A line terminator is excluded because the published pattern must mean the same thing as this method, and
 *   a JSON Schema `pattern` is ECMA-262, where `.` matches everything except exactly these four code points.
 *   An id spanning two lines also serves no part of the naming purpose a free-form id exists for.
 *
 * Invalid UTF-8 cannot reach here and is therefore not handled: an HTTP payload fails `json_decode` first,
 * the stored column is MySQL `JSON`, and a PHP caller would fail at the encode before the write. That is why
 * the scan is {@see \str_contains} over whole code points rather than a `preg_match` needing the `u`
 * modifier, whose `false` return on malformed input would read as "admitted".
 *
 * @internal
 */
#[Package('framework')]
final class ElementIdRule
{
    /**
     * ECMA-262's `LineTerminator` production, keyed by the code point each one publishes in a message. This
     * is deliberately narrower than Unicode's newline set: NEL (U+0085), vertical tab and form feed are not
     * line terminators in ECMA-262, so `.` matches them and both sides admit them.
     */
    public const LINE_TERMINATORS = [
        "\n" => 'U+000A',
        "\r" => 'U+000D',
        "\u{2028}" => 'U+2028',
        "\u{2029}" => 'U+2029',
    ];

    /**
     * The predicate an enforcement site frames for its own audience — `it …` for the decode throw, `This
     * value …` for the write violation — or null when the id is admitted.
     */
    public static function rejection(string $id): ?string
    {
        if ($id === VirtualRootWrapper::VIRTUAL_ROOT_ID) {
            return 'is the reserved virtual-root id';
        }

        if (!\is_string(array_key_first([$id => null]))) {
            return 'is a string PHP casts to an integer array key';
        }

        foreach (self::LINE_TERMINATORS as $terminator => $codePoint) {
            if (\str_contains($id, $terminator)) {
                return 'contains the line terminator ' . $codePoint;
            }
        }

        return null;
    }
}
