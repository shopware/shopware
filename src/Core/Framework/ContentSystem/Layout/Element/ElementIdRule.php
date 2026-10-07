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
    private const LINE_TERMINATORS = ["\n", "\r", "\u{2028}", "\u{2029}"];

    /**
     * Canonical integer literals only. `012` therefore stays admitted, which is what keeps a minted hex id —
     * 32 characters, leading `0` — outside the rule even when every character happens to be a digit.
     */
    private const INTEGER_LITERAL = '/^-?(0|[1-9][0-9]*)\z/';

    /**
     * Why the id is refused, or null when it is admitted.
     *
     * Line terminators are found with `str_contains` rather than a `preg_match` needing the `u` modifier,
     * whose `false` return on malformed UTF-8 would read as "admitted".
     */
    public static function rejection(string $id): ?ElementIdRejection
    {
        if ($id === VirtualRootWrapper::VIRTUAL_ROOT_ID) {
            return ElementIdRejection::ReservedLiteral;
        }

        if (preg_match(self::INTEGER_LITERAL, $id) === 1) {
            return ElementIdRejection::IntegerLiteral;
        }

        foreach (self::LINE_TERMINATORS as $terminator) {
            if (\str_contains($id, $terminator)) {
                return ElementIdRejection::LineTerminator;
            }
        }

        return null;
    }
}
