<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\PregDollarWithoutDRule;

use Symfony\Component\Finder\Finder;

class FinderUsage
{
    public function filters(Finder $finder, string $runtime): void
    {
        // Finder applies an argument as a regex only when it reads like one, anything else is a glob
        // a second comment line keeps the flagged lines aligned with the test
        $finder->name('/\.php$/'); // flagged
        $finder->notPath('/\.test\.(js|ts)$/'); // flagged
        $finder->path(['#^src/.+$#', '*.txt']); // flagged once: the glob is skipped
        $finder->notName('{\.tmp$}'); // flagged: bracket delimiters
        $finder->contains('/^foo$/i'); // flagged
        $finder->name('*.php'); // ok: glob
        $finder->name('/\.php$/D'); // ok: D
        $finder->name($runtime); // ok: unreadable arguments are not reported, Finder may read them as globs
        $finder->notContains('/x\z/'); // ok: \z
    }
}
