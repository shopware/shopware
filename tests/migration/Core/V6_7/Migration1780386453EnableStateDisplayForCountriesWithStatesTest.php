<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1780386453EnableStateDisplayForCountriesWithStates;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1780386453EnableStateDisplayForCountriesWithStates::class)]
class Migration1780386453EnableStateDisplayForCountriesWithStatesTest extends TestCase
{
    private Connection $connection;

    private Migration1780386453EnableStateDisplayForCountriesWithStates $migration;

    /**
     * @var list<array{id: string, displayStateInRegistration: int}>
     */
    private array $originalStateCountryDisplayStates = [];

    /**
     * @var list<string>
     */
    private array $countryIds = [];

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1780386453EnableStateDisplayForCountriesWithStates();

        $countriesWithStates = $this->connection->fetchAllAssociative('
            SELECT DISTINCT c.`id`, c.`display_state_in_registration`
            FROM `country` c
            INNER JOIN `country_state` cs
                ON cs.`country_id` = c.`id`
        ');

        foreach ($countriesWithStates as $country) {
            $this->originalStateCountryDisplayStates[] = [
                'id' => (string) $country['id'],
                'displayStateInRegistration' => (int) $country['display_state_in_registration'],
            ];
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalStateCountryDisplayStates as $country) {
            $this->connection->update(
                'country',
                ['display_state_in_registration' => $country['displayStateInRegistration']],
                ['id' => $country['id']]
            );
        }

        foreach ($this->countryIds as $countryId) {
            $this->connection->delete('country', ['id' => $countryId]);
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1780386453, $this->migration->getCreationTimestamp());
    }

    public function testEnablesStateDisplayOnlyForCountriesWithStates(): void
    {
        $countryWithStateId = $this->createCountry(false);
        $countryWithoutStateId = $this->createCountry(false);
        $countryWithStateAndEnabledDisplayId = $this->createCountry(true);

        $this->createCountryState($countryWithStateId);
        $this->createCountryState($countryWithStateAndEnabledDisplayId);

        static::assertSame(0, $this->fetchDisplayState($countryWithStateId));
        static::assertSame(0, $this->fetchDisplayState($countryWithoutStateId));
        static::assertSame(1, $this->fetchDisplayState($countryWithStateAndEnabledDisplayId));

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        static::assertSame(1, $this->fetchDisplayState($countryWithStateId));
        static::assertSame(0, $this->fetchDisplayState($countryWithoutStateId));
        static::assertSame(1, $this->fetchDisplayState($countryWithStateAndEnabledDisplayId));
    }

    private function createCountry(bool $displayStateInRegistration): string
    {
        $countryId = Uuid::randomBytes();
        $this->countryIds[] = $countryId;

        $this->connection->insert('country', [
            'id' => $countryId,
            'iso' => 'X' . \count($this->countryIds),
            'iso3' => 'X' . \count($this->countryIds) . 'T',
            'display_state_in_registration' => $displayStateInRegistration ? 1 : 0,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $countryId;
    }

    private function createCountryState(string $countryId): void
    {
        $this->connection->insert('country_state', [
            'id' => Uuid::randomBytes(),
            'country_id' => $countryId,
            'short_code' => 'TEST-' . Uuid::randomHex(),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function fetchDisplayState(string $countryId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT `display_state_in_registration` FROM `country` WHERE `id` = :countryId',
            ['countryId' => $countryId]
        );
    }
}
