<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemCreationDateRule extends Rule
{
    /**
     * @var string|null
     */
    protected $lineItemCreationDate;

    public function getName(): string
    {
        return 'cartLineItemCreationDate';
    }

    public function match(RuleScope $scope): bool
    {
        if ($this->lineItemCreationDate === null) {
            return false;
        }

        try {
            $ruleDefinedCreationDateTime = $this->createDateTime($this->lineItemCreationDate);
        } catch (\Exception $e) {
            return false;
        }

        if ($scope instanceof LineItemScope) {
            return $this->matchesCreationDate($scope->getLineItem(), $ruleDefinedCreationDateTime);
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems() as $lineItem) {
            if ($this->matchesCreationDate($lineItem, $ruleDefinedCreationDateTime)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'lineItemCreationDate' => [new NotBlank(), new Type('string')],
        ];
    }

    /**
     * @throws PayloadKeyNotFoundException
     */
    private function matchesCreationDate(LineItem $lineItem, \DateTime $ruleDefinedCreationDateTime): bool
    {
        $createdAtString = $lineItem->getPayloadValue('createdAt');

        if ($createdAtString === null) {
            return false;
        }

        /* @var string $createdAtString */
        try {
            $createdAt = $this->createDateTime($createdAtString);
        } catch (\Exception $e) {
            return false;
        }

        $diff = $createdAt->diff($ruleDefinedCreationDateTime);

        return $diff->days === 0;
    }

    /**
     * @throws \Exception
     */
    private function createDateTime(string $dateString): \DateTime
    {
        $dateTime = new \DateTime($dateString);
        $dateTime->setTime(0, 0, 0);

        return $dateTime;
    }
}
