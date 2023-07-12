<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;
use Shopware\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeCollection;
use Shopware\Core\System\Tax\TaxRuleType\EntireCountryRuleTypeFilter;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_5\Migration1688927492AddTaxActiveFromField
 */
class Migration1688927492AddTaxActiveFromFieldTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private EntityRepository $taxRepository;
    private EntityRepository $taxRuleRepository;
    private TaxRuleTypeCollection $taxRuleTypes;

    protected function setUp(): void
    {
        $this->taxRuleTypes = $this->loadTaxRuleTypes();
        $this->taxRepository = $this->getContainer()->get('tax.repository');
        $this->taxRuleRepository = $this->getContainer()->get('tax_rule.repository');
    }

    public function testTaxRuleAfterMigration(): void
    {
        // GIVEN
        $taxId = Uuid::randomHex();
        $taxRuleId = Uuid::randomHex();
        $activeFrom = new \DateTime();
        $context = Context::createDefaultContext();
        $taxData = [
            'id' => $taxId,
            'name' => 'test',
            'taxRate' => 7.125,
            'rules' => [
                [
                    'id' => $taxRuleId,
                    'taxRate' => 10,
                    'activeFrom' => $activeFrom,
                    'country' => [
                        'name' => 'test'
                    ],
                    'taxRuleTypeId' => $this->taxRuleTypes->getByTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ]
            ]
        ];

        // THEN
        $this->taxRepository->create([$taxData], $context);
        /** @var TaxRuleEntity $taxRule */
        $taxRule = $this->taxRuleRepository->search(new Criteria([$taxRuleId]), $context)->first();

        // THEN
        $this->assertEquals($activeFrom->getTimestamp(), $taxRule->getActiveFrom()->getTimestamp());
    }

    private function loadTaxRuleTypes(): TaxRuleTypeCollection
    {
        /** @var TaxRuleTypeCollection $collection */
        $collection = $this->getContainer()->get('tax_rule_type.repository')->search(new Criteria(), Context::createDefaultContext())->getEntities();

        return $collection;
    }
}
