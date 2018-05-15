<?php declare(strict_types=1);

namespace Shopware\System\Tax\Struct;

use Shopware\System\Tax\Collection\TaxAreaRuleBasicCollection;

class TaxDetailStruct extends TaxBasicStruct
{
    /**
     * @var TaxAreaRuleBasicCollection
     */
    protected $areaRules;

    public function __construct()
    {
        $this->areaRules = new TaxAreaRuleBasicCollection();
    }

    public function getAreaRules(): TaxAreaRuleBasicCollection
    {
        return $this->areaRules;
    }

    public function setAreaRules(TaxAreaRuleBasicCollection $areaRules): void
    {
        $this->areaRules = $areaRules;
    }
}
