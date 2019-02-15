<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write\ValueGenerator;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Write\ValueGenerator\NumberRangeValueGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Write\ValueGenerator\SqlValueGeneratorConnector;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\NumberRange\NumberRangeEntity;

class SqlValueGeneratorConnectorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetState(): void
    {
        $connection = $this->createMock(Connection::class);
        $stm = $this->createMock(Statement::class);
        $connector = new SqlValueGeneratorConnector($connection);
        $connector->setGenerator($this->getGenerator());

        $connection->expects(self::any())->method('executeQuery')->willReturn($stm);
        $stm->expects(self::once())->method('fetchColumn')->willReturn(5);

        $state = $connector->pullState();
        self::assertEquals(5, $state);
    }

    private function getGenerator(): NumberRangeValueGenerator
    {
        $generator = new NumberRangeValueGenerator();
        $configuration = new NumberRangeEntity();
        $configuration->setConnectorType('standard_value_generator_connector');
        $configuration->setGeneratorType('number_range_value_generator');
        $configuration->setPrefix('Pre_');
        $configuration->setSuffix('_suf');
        $generator->setConfiguration($configuration);

        return $generator;
    }
}
