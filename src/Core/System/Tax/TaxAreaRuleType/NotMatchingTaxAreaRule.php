<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\TaxAreaRuleType;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class NotMatchingTaxAreaRule extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $technicalName;

    public function __construct(string $technicalName)
    {
        parent::__construct(sprintf('Rule with type %s does not match', $technicalName));
        $this->technicalName = $technicalName;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__TAX_AREA_RULE_NOT_MATCHED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
