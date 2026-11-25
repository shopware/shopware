<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Encoder;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Event\RenderedTreeFinalizationEvent;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndex;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueOrigin;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;

/**
 * Turns a render result's {@see ResolvedValueIndex} into the two wire maps every index-reading format serves:
 * `data`, one ref to the value it holds, and `assignments`, one element id to its property keys to their refs.
 * The decomposed format serves the pair under its structure and the data format serves it alone; neither
 * derives it from the other, so the pair is encoded here once for both.
 *
 * Every `Struct` leaf goes through {@see StructEncoder::encode()}, which is where the framework's protection gate
 * lives (ApiAware flags, `isProtected`, the customFields blocklist). That per-leaf delegation is load-bearing
 * rather than convenient: it is the only thing applying the gate to an entity payload on this path, so a leaf
 * that skipped it would publish every field of it. A non-`Struct` object value passes through untouched. What
 * is never safe is a non-`Struct` object holding a `Struct` anywhere in its object graph: this method sees one
 * opaque wrapper, so the contained `Struct` goes past the gate unrun. The index carries every rendered
 * property value whatever its {@see ValueOrigin}, so the
 * bar is on whatever produced the value rather than on the subscribers of one event — a
 * {@see RenderedTreeFinalizationEvent} listener writing under
 * {@see ValueOrigin::Injected} is the widest producer, not
 * the only one. {@see RenderedElement}'s constructor closes that opening for every one of them: it admits a
 * property value only from a closed domain — scalar, null, arrays recursively of the same domain, `Struct`,
 * `\DateTimeInterface` and `\BackedEnum` — so a non-`Struct` object concealing a `Struct` is rejected where it
 * is written rather than published from here.
 *
 * The index is optional output on the render result, but not for the formats that read it: the pipeline builds
 * one whenever the format asks for it, and both formats reading it always ask. A missing index is therefore a
 * broken wiring invariant rather than a state to serve around — an empty `data` map cannot be told apart from
 * a page whose elements resolved nothing.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ResolvedValueIndexEncoder
{
    public function __construct(private readonly StructEncoder $structEncoder)
    {
    }

    /**
     * @return array{data: array<string, mixed>, assignments: array<string, array<string, string>>}
     */
    public function encode(RenderResult $result): array
    {
        if ($result->index === null) {
            throw ContentSystemException::resolvedValueIndexMissing($result->reference->id);
        }

        return [
            'data' => array_map($this->encodeValue(...), $result->index->data()),
            'assignments' => $result->index->assignments(),
        ];
    }

    /**
     * Descends into arrays because a ref may hold a list or map of entities without a `Collection` around them;
     * a `Struct` at any depth still reaches the protection gate.
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
