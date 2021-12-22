<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Changelog;

use Symfony\Component\Filesystem\Filesystem;

trait ChangelogTestBehaviour
{
    /**
     * @before
     */
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

    /**
     * @after
     */
    public function afterChangelogTest(): void
    {
        (new Filesystem())
            ->remove(__DIR__ . '/_fixture/stage');
    }
}
