<?php declare(strict_types=1);

namespace Core\System\Test\SystemConfig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigCollection;

class SystemConfigRepositoryTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private const CONFIG_KEY = 'testKey';
    private const CONFIG_VALUE = 'testValue';

    public function testFilterByValue(): void
    {
        /** @var EntityRepositoryInterface $repo */
        $repo = $this->getContainer()->get('system_config.repository');
        $context = Context::createDefaultContext();

        $data = [[
            'configurationKey' => self::CONFIG_KEY,
            'configurationValue' => self::CONFIG_VALUE,
        ]];

        $repo->upsert($data, $context);

        $filterByKeyCriteria = (new Criteria())->addFilter(new EqualsFilter('configurationKey', self::CONFIG_KEY));
        /** @var SystemConfigCollection $configs */
        $configs = $repo->search($filterByKeyCriteria, $context)->getEntities();
        static::assertCount(1, $configs);

        $firstConfig = $configs->first();
        static::assertNotNull($firstConfig);
        static::assertSame(self::CONFIG_VALUE, $firstConfig->getConfigurationValue());

        $filterByValueCriteria = (new Criteria())->addFilter(new EqualsFilter('configurationValue', self::CONFIG_VALUE));
        /** @var SystemConfigCollection $configs */
        $configs = $repo->search($filterByValueCriteria, $context)->getEntities();
        static::assertCount(1, $configs);

        $firstConfig = $configs->first();
        static::assertNotNull($firstConfig);
        static::assertSame(self::CONFIG_KEY, $firstConfig->getConfigurationKey());
    }
}
