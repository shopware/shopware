<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @package system-settings
 *
 * @phpstan-type VersionFixedVulnerabilities array{severity: string, summary: string, link: string}
 */
#[Package('system-settings')]
class Version extends Struct
{
    public string $title = '';

    public string $body = '';

    public \DateTimeImmutable $date;

    public string $version = '';

    /**
     * @var VersionFixedVulnerabilities[]
     */
    public array $fixedVulnerabilities = [];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->date = new \DateTimeImmutable();

        if (isset($data['date']) && \is_string($data['date'])) {
            $data['date'] = new \DateTimeImmutable($data['date']);
        }

        $this->assign($data);
    }

    public function getApiAlias(): string
    {
        return 'update_api_version';
    }
}
