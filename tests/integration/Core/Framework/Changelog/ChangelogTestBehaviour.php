<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Changelog;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Component\Filesystem\Filesystem;

trait ChangelogTestBehaviour
{
    #[Before]
    public function beforeChangelogTest(): void
    {
        $fs = new Filesystem();

        $fs->mkdir(__DIR__ . '/_fixture/template');
        $fs->mirror(
            __DIR__ . '/_fixture/template/',
            __DIR__ . '/_fixture/stage/',
            null,
            ['override' => true, 'delete' => true]
        );
    }

    #[After]
    public function afterChangelogTest(): void
    {
        (new Filesystem())
            ->remove(__DIR__ . '/_fixture/stage');
    }
}
