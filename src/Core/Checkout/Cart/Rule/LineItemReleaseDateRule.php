<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemReleaseDateRule extends Rule
{
    /**
     * @var string|null
     */
    protected $lineItemReleaseDate;

    public function getName(): string
    {
        return 'cartLineItemReleaseDate';
    }

    public function match(RuleScope $scope): bool
    {
        if ($this->lineItemReleaseDate === null) {
            return false;
        }

        try {
            $ruleDefinedReleaseDateTime = $this->createDateTime($this->lineItemReleaseDate);
        } catch (\Exception $e) {
            return false;
        }

        if ($scope instanceof LineItemScope) {
            return $this->matchesReleaseDate($scope->getLineItem(), $ruleDefinedReleaseDateTime);
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems() as $lineItem) {
            if ($this->matchesReleaseDate($lineItem, $ruleDefinedReleaseDateTime)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'lineItemReleaseDate' => [new NotBlank(), new Type('string')],
        ];
    }

    /**
     * @throws PayloadKeyNotFoundException
     */
    private function matchesReleaseDate(LineItem $lineItem, \DateTime $ruleDefinedReleaseDateTime): bool
    {
        $releasedAtString = $lineItem->getPayloadValue('releaseDate');

        if ($releasedAtString === null) {
            return false;
        }

        /* @var string $releasedAtString */
        try {
            $releasedAt = $this->createDateTime($releasedAtString);
        } catch (\Exception $e) {
            return false;
        }

        $diff = $releasedAt->diff($ruleDefinedReleaseDateTime);

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
