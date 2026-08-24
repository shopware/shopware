<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Index;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * Every value one response serves, held once, plus the map saying which element property points at which of
 * them. Built by {@see ResolvedValueIndexFactory} and read back afterwards; nothing here computes on read,
 * which is what separates an index from a resolver.
 *
 * Refs are response-local: they are assigned while walking one finished tree and carry no meaning outside the
 * response they were minted in. Nothing may store one, and nothing may assert a literal ref id.
 *
 * The two guards below are grammar invariants, not input validation. A served layout is stored data rather
 * than something a client sent, so a violation is an internal fault (HTTP 500) and never a client defect: the
 * only way to get here is a producer that built the two maps inconsistently.
 */
#[Package('framework')]
final readonly class ResolvedValueIndex
{
    /**
     * @param array<string, mixed> $data ref => the value it holds
     * @param array<string, array<string, string>> $assignments element id => property key => ref
     */
    public function __construct(
        private array $data,
        private array $assignments,
    ) {
        $this->rejectDanglingRefs();
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function assignments(): array
    {
        return $this->assignments;
    }

    /**
     * Throws on a ref it does not know rather than answering null, because a ref legitimately holds null — a
     * loader that ran and found nothing — so null would conflate "this ref holds nothing" with "there is no
     * such ref". The membership test is therefore `array_key_exists` and not a `??`.
     *
     * @throws ContentSystemException
     */
    public function value(string $ref): mixed
    {
        if (!\array_key_exists($ref, $this->data)) {
            throw ContentSystemException::invalidMapValue(
                'Resolved value index lookup',
                $ref,
                'a ref present in the index data',
                'no such ref'
            );
        }

        return $this->data[$ref];
    }

    /**
     * Every ref an assignment names must exist in the data map. An assignment pointing at nothing is a
     * property whose value the response cannot serve at all, and the consumer resolving that ref would either
     * emit null for a value that is not null or fail somewhere far from here.
     */
    private function rejectDanglingRefs(): void
    {
        foreach ($this->assignments as $elementId => $refs) {
            foreach ($refs as $key => $ref) {
                if (\array_key_exists($ref, $this->data)) {
                    continue;
                }

                throw ContentSystemException::invalidMapValue(
                    'Resolved value index assignment',
                    \sprintf('%s.%s', $elementId, $key),
                    'a ref present in the index data',
                    \sprintf('unknown ref "%s"', $ref)
                );
            }
        }
    }
}
