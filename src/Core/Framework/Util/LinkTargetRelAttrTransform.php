<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class LinkTargetRelAttrTransform extends \HTMLPurifier_AttrTransform
{
    private const SAME_BROWSING_CONTEXT_TARGETS = ['_self', '_parent', '_top'];

    /**
     * @param list<string> $rels
     */
    public function __construct(private readonly array $rels)
    {
    }

    /**
     * @param array<string, string> $attr
     * @param \HTMLPurifier_Config $config
     * @param \HTMLPurifier_Context $context
     *
     * @return array<string, string>
     */
    public function transform($attr, $config, $context): array
    {
        $target = strtolower($attr['target'] ?? '');

        if ($target === '' || \in_array($target, self::SAME_BROWSING_CONTEXT_TARGETS, true)) {
            return $attr;
        }

        $rels = preg_split('/\s+/', $attr['rel'] ?? '', -1, \PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($this->rels as $rel) {
            if (!\in_array($rel, $rels, true)) {
                $rels[] = $rel;
            }
        }

        $attr['rel'] = implode(' ', $rels);

        return $attr;
    }
}
