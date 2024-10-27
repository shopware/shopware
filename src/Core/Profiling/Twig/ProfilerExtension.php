<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Twig;

use Cocur\Slugify\Slugify;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Profiler;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[Package('core')]
class ProfilerExtension extends AbstractExtension
{
    public function __construct(private readonly Slugify $slugify)
    {
    }

    public function getTokenParsers(): array
    {
        return [new ProfileTokenParser()];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('profiler_start', $this->start(...)),
            new TwigFunction('profiler_stop', $this->stop(...)),
        ];
    }

    public function start(?string $title, string $category = 'sw-template'): void
    {
        if ($title === null) {
            return;
        }
        Profiler::start(title: $this->slugify($title), category: $category, tags: []);
    }

    public function stop(?string $title): void
    {
        if ($title === null) {
            return;
        }
        Profiler::stop(title: $this->slugify($title));
    }

    private function slugify(string $title): string
    {
        return strtolower($this->slugify->slugify($title));
    }
}
