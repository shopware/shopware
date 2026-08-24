<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Encoder;

use Shopware\Core\Framework\ContentSystem\Event\RenderedTreeFinalizationEvent;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Output\Struct\EncodedContentPage;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;

/**
 * Turns one finished render into the full format's response body. The module owns the tree shape from here on:
 * which keys an element carries, what a slot looks like, and the `apiAlias` every node reports. `RenderedElement`
 * is deliberately not a `Struct`, so `StructEncoder` cannot walk the tree itself and would not be told to — a
 * response model the framework encoder cannot traverse is the point of the split, not an obstacle to it.
 *
 * The one thing the module does NOT own is what an entity payload may say. Every `Struct`-valued property leaf
 * goes through {@see StructEncoder::encode()}, which is where the framework's protection gate lives (ApiAware
 * flags, `isProtected`, the customFields blocklist). That per-leaf delegation is load-bearing rather than
 * convenient: the encoder is the only place a hydrated entity reaches the wire on this path, so a leaf that
 * skipped the gate would publish every field of it.
 *
 * A non-`Struct` object value is passed through untouched, which keeps the treatment such a value has today —
 * the carrier's JSON round trip serializes it. What is never safe is a non-`Struct` object holding a `Struct`
 * anywhere in its object graph: this method sees one opaque wrapper, so the `Struct` inside it never reaches
 * the gate above and every field of it would be published. That is a property of the value, so the bar is on
 * whatever produced the value rather than on the subscribers of one event. The tiers that fill a rendered
 * property differ only in how wide the opening is: a stored value is a JSON value and a loader value is a
 * `?Struct`, while a delivered context value is whatever a dotted consumer key found on an entity, and a
 * {@see RenderedTreeFinalizationEvent} listener replacing the
 * rendered forest may write anything at all under any key. {@see RenderedElement}'s constructor closes that
 * opening for all of them: it admits a property value only from a closed domain — scalar, null, arrays
 * recursively of the same domain, `Struct`, `\DateTimeInterface` and `\BackedEnum` — so a non-`Struct` object
 * concealing a `Struct` is rejected where it is written rather than published from here.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentPageEncoder
{
    /**
     * The wire aliases of the full format. They are external contract strings rather than PHP names: neither
     * is derived from a class, so renaming or removing a producing class never moves them.
     */
    public const PAGE_API_ALIAS = 'content_page';

    public const ELEMENT_API_ALIAS = 'content_element';

    public function __construct(private readonly StructEncoder $structEncoder)
    {
    }

    public function encode(RenderResult $result): EncodedContentPage
    {
        return new EncodedContentPage([
            'id' => $result->reference->id,
            'name' => $result->reference->name,
            'version' => $result->reference->version,
            'elements' => array_map($this->encodeElement(...), $result->tree),
        ], self::PAGE_API_ALIAS);
    }

    /**
     * `id`, `component` and `properties` are always present; `slots` and `style` are omitted when empty. The
     * `apiAlias` goes last on every node at every depth, which is the shape the framework encoder produces for
     * a top-level struct and the shape this format now keeps all the way down.
     *
     * @return array<string, mixed>
     */
    private function encodeElement(RenderedElement $element): array
    {
        $data = [
            'id' => $element->id,
            'component' => $element->component,
            'properties' => $this->encodeProperties($element->properties),
        ];

        if ($element->slots !== []) {
            $slots = [];
            foreach ($element->slots as $name => $children) {
                $slots[$name] = array_map($this->encodeElement(...), $children);
            }

            $data['slots'] = $slots;
        }

        if (!$element->style->isEmpty()) {
            $data['style'] = $element->style->toArray();
        }

        $data['apiAlias'] = self::ELEMENT_API_ALIAS;

        return $data;
    }

    /**
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    private function encodeProperties(array $properties): array
    {
        return array_map($this->encodeValue(...), $properties);
    }

    /**
     * Descends into arrays because a property may hold a list or map of entities without a `Collection` around
     * them; a `Struct` at any depth still reaches the protection gate.
     */
    private function encodeValue(mixed $value): mixed
    {
        if ($value instanceof Struct) {
            return $this->structEncoder->encode($value, new ResponseFields());
        }

        if (\is_array($value)) {
            return array_map($this->encodeValue(...), $value);
        }

        return $value;
    }
}
