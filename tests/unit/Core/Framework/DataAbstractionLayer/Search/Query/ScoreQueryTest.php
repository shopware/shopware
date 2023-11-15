<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Query;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;

/**
 * @internal
 */
class ScoreQueryTest extends TestCase
{

    public function testJsonSerialization(): void
    {
        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('productNumber', '123456'), 100));

        /**
         * @see \Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator::getCriteriaHash
         */
        $json = json_encode($criteria->getQueries(), JSON_THROW_ON_ERROR);

        $expected = '[{"extensions":[],"_class":"Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\Search\\\\Query\\\\ScoreQuery","query":{"extensions":[],"isPrimary":false,"resolved":null,"field":"productNumber","value":"123456","_class":"Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\Search\\\\Filter\\\\ContainsFilter"},"score":100,"scoreField":null}]';
        static::assertEquals($expected, $json);

    }
}
