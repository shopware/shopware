<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

/**
 * @feature-deprecated (flag:FEATURE_NEXT_12158) tag:v6.4.0 - Use extendDocuments instead
 */
class FullText
{
    protected string $fullText;

    protected string $boosted;

    public function __construct(string $fullText, string $boosted)
    {
        $this->fullText = $fullText;
        $this->boosted = $boosted;
    }

    public function getFullText(): string
    {
        return $this->fullText;
    }

    public function getBoosted(): string
    {
        return $this->boosted;
    }
}
