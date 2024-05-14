<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Staging\Handler;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;

/**
 * @internal
 *
 * @phpstan-type DomainRewriteRule = array{match: string, type: string, replace: string}
 * @phpstan-type DomainURL = array{id: string, url: string}
 */
#[Package('core')]
readonly class StagingSalesChannelHandler
{
    /**
     * @param DomainRewriteRule[] $rewrite
     */
    public function __construct(
        private array $rewrite,
        private Connection $connection
    ) {
    }

    public function __invoke(SetupStagingEvent $event): void
    {
        /** @var DomainURL[] $urls */
        $urls = $this->connection->fetchAllAssociative('SELECT id, url FROM sales_channel_domain');

        $changes = [];

        foreach ($urls as $urlRecord) {
            $beforeURl = $urlRecord['url'];

            foreach ($this->rewrite as $rule) {
                switch ($rule['type']) {
                    case 'equal':
                        $urlRecord = $this->modifyByEqual($urlRecord, $rule);

                        break;
                    case 'regex':
                        $urlRecord = $this->modifyByRegex($urlRecord, $rule);

                        break;
                    case 'prefix':
                        if (str_starts_with($urlRecord['url'], $rule['match'])) {
                            $urlRecord['url'] = $rule['replace'] . substr($urlRecord['url'], \strlen($rule['match']));
                        }

                        break;
                }
            }

            if ($beforeURl !== $urlRecord['url']) {
                $changes[] = [$beforeURl, $urlRecord['url']];
                $this->connection->update('sales_channel_domain', ['url' => $urlRecord['url']], ['id' => $urlRecord['id']]);
            }
        }

        if ($changes !== []) {
            $event->io->table(['Before URL', 'After URL'], $changes);
        }
    }

    /**
     * @param DomainURL $urlRecord
     * @param DomainRewriteRule $rule
     *
     * @return DomainURL
     */
    private function modifyByEqual(array $urlRecord, array $rule): array
    {
        if ($urlRecord['url'] !== $rule['match']) {
            return $urlRecord;
        }

        $urlRecord['url'] = $rule['replace'];

        return $urlRecord;
    }

    /**
     * @param DomainURL $urlRecord
     * @param DomainRewriteRule $rule
     *
     * @return DomainURL
     */
    private function modifyByRegex(array $urlRecord, array $rule): array
    {
        if (preg_match($rule['match'], $urlRecord['url'])) {
            $urlRecord['url'] = (string) preg_replace($rule['match'], $rule['replace'], $urlRecord['url']);
        }

        return $urlRecord;
    }
}
