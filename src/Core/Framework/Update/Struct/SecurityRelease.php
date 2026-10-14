<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Clock\Clock;

/**
 * @phpstan-import-type VersionFixedVulnerabilities from Version
 */
#[Package('framework')]
class SecurityRelease extends Struct
{
    public bool $isSecurityRelease = false;

    public string $version = '';

    public string $title = '';

    public \DateTimeImmutable $date;

    public string $highestSeverity = '';

    public int $totalVulnerabilities = 0;

    /**
     * @var VersionFixedVulnerabilities[]
     */
    public array $vulnerabilities = [];

    public function __construct()
    {
        $this->date = Clock::get()->now();
    }

    public function getApiAlias(): string
    {
        return 'update_api_security_release';
    }
}
