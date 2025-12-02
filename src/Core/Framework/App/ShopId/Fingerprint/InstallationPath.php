<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId\Fingerprint;

use Shopware\Core\Framework\App\ShopId\Fingerprint;
use Shopware\Core\Framework\App\ShopId\FingerprintCustomCompare;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
readonly class InstallationPath implements Fingerprint, FingerprintCustomCompare
{
    final public const IDENTIFIER = 'installation_path';

    public function __construct(
        private string $projectDir,
    ) {
    }

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getScore(): int
    {
        return 100;
    }

    public function getStamp(): string
    {
        return $this->projectDir;
    }

    /**
     * A complete change in path is a score of 100,
     * A partial change at the end is low score
     * A partial change at the beginning or drastic change is a high score
     *
     * @see \Shopware\Tests\Unit\Core\Framework\App\ShopId\Fingerprint\InstallationPathTest::testCompare for examples
     */
    public function compare(?string $storedStamp): int
    {
        if ($storedStamp === null) {
            return $this->getScore();
        }

        $newStamp = $this->getStamp();

        if ($storedStamp === $newStamp) {
            return 0;
        }

        $storedPathParts = explode('/', trim($storedStamp, '/'));
        $newPathParts = explode('/', trim($newStamp, '/'));

        $maxParts = max(\count($storedPathParts), \count($newPathParts));

        $results = [];
        for ($i = 0; $i < $maxParts; ++$i) {
            $storedPart = $storedPathParts[$i] ?? null;
            $newPart = $newPathParts[$i] ?? null;

            $previousResult = $results[$i - 1] ?? true;

            // if the last path part matched and this one, record match
            // if the previous failed, then this should not also
            $results[$i] = $previousResult && $storedPart === $newPart;
        }

        $numFailures = \count(array_filter($results, fn ($result) => $result === false));

        return (int) (100 / $maxParts) * $numFailures;
    }
}
