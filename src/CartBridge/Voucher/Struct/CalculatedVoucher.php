<?php
declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\CartBridge\Voucher\Struct;

use Shopware\Api\Media\Struct\MediaBasicStruct;
use Shopware\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Cart\LineItem\LineItemInterface;
use Shopware\Cart\Price\Struct\CalculatedPrice;
use Shopware\Context\Rule\Rule;
use Shopware\Context\Rule\Validatable;
use Shopware\Framework\Struct\Struct;

class CalculatedVoucher extends Struct implements CalculatedLineItemInterface, Validatable
{
    /**
     * @var LineItemInterface
     */
    protected $lineItem;

    /**
     * @var CalculatedPrice
     */
    protected $price;

    /**
     * @var string
     */
    protected $code;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var Rule|null
     */
    protected $rule;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var MediaBasicStruct|null
     */
    protected $cover;

    public function __construct(
        string $code,
        LineItemInterface $lineItem,
        CalculatedPrice $price,
        string $label,
        ?Rule $rule = null,
        ?string $description = null,
        ?MediaBasicStruct $cover = null
    ) {
        $this->price = $price;
        $this->lineItem = $lineItem;
        $this->code = $code;
        $this->identifier = $this->lineItem->getIdentifier();
        $this->rule = $rule;
        $this->label = $label;
        $this->description = $description;
        $this->cover = $cover;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getPrice(): CalculatedPrice
    {
        return $this->price;
    }

    public function getLineItem(): ? LineItemInterface
    {
        return $this->lineItem;
    }

    public function getCalculatedLineItem(): CalculatedLineItemInterface
    {
        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCover(): ?MediaBasicStruct
    {
        return $this->cover;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getQuantity(): int
    {
        return $this->lineItem->getQuantity();
    }

    public function getRule(): ? Rule
    {
        return $this->rule;
    }

    public function getType(): string
    {
        return $this->lineItem->getType();
    }
}
