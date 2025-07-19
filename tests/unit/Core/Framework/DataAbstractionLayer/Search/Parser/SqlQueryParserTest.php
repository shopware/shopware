<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Parser;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\System\Unit\UnitDefinition;

/**
 * @internal
 */
#[CoversClass(SqlQueryParser::class)]
class SqlQueryParserTest extends TestCase
{
    public function testParseUnsupportedQueryFilter(): void
    {
        $this->expectException(DataAbstractionLayerException::class);

        $parser = new SqlQueryParser(new EntityDefinitionQueryHelper(), $this->createMock(Connection::class));

        $parser->parse(
            new ScoreQuery(new ContainsFilter('description', 'test'), 250),
            new UnitDefinition(),
            Context::createDefaultContext(),
        );
    }
}
