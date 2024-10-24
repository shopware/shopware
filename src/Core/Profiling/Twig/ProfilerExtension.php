<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Twig;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Profiler;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[Package('core')]
class ProfilerExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('profiler_start', $this->start(...)),
            new TwigFunction('profiler_end', $this->end(...)),
        ];
    }

    public function start(string $title, string $category = 'shopware-template'): void
    {
        Profiler::start(title: $title, category: $category, tags: []);
    }

    public function end(string $title): void
    {
        Profiler::stop(title: $title);
    }
}
