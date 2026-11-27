<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Export\Summarizer;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * @internal
 */
#[Package('framework')]
interface PanelSummarizer
{
    public function supports(DataCollectorInterface $collector): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function summarize(DataCollectorInterface $collector, mixed $data): ?array;
}
