<?php declare(strict_types=1);

namespace Shopware\Recovery\Common;

class SystemLocker
{
    /**
     * @var string
     */
    private $lockfile;

    public function __construct(string $lockfile)
    {
        $this->lockfile = $lockfile;
    }

    public function __invoke(): void
    {
        file_put_contents($this->lockfile, date('YmdHi'));
    }
}
