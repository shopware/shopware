<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Export\Summarizer;

use Shopware\Core\Framework\Log\Package;
use Symfony\Bridge\Twig\DataCollector\TwigDataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * @internal
 */
#[Package('framework')]
class TwigSummarizer implements PanelSummarizer
{
    private const MAX_TEMPLATES = 20;

    public function supports(DataCollectorInterface $collector): bool
    {
        return $collector instanceof TwigDataCollector;
    }

    public function summarize(DataCollectorInterface $collector, mixed $data): array
    {
        \assert($collector instanceof TwigDataCollector);

        $templates = $collector->getTemplates();
        arsort($templates);

        return [
            'timeMs' => round($collector->getTime(), 1),
            'templates' => $collector->getTemplateCount(),
            'blocks' => $collector->getBlockCount(),
            'macros' => $collector->getMacroCount(),
            'mostRendered' => \array_slice($templates, 0, self::MAX_TEMPLATES, true),
        ];
    }
}
