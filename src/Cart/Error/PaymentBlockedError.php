<?php declare(strict_types=1);
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

namespace Shopware\Cart\Error;

class PaymentBlockedError extends Error
{
    /**
     * @var int
     */
    protected $paymentId;

    /**
     * @var string
     */
    protected $paymentName;

    public function __construct(int $paymentId, string $paymentName)
    {
        $this->paymentId = $paymentId;
        $this->paymentName = $paymentName;
    }

    public function getPaymentId(): int
    {
        return $this->paymentId;
    }

    public function getPaymentName(): string
    {
        return $this->paymentName;
    }

    public function getMessageKey(): string
    {
        return self::class;
    }

    public function getMessage(): string
    {
        return sprintf('Current payment method %s (id: %s) blocked', $this->paymentName, $this->paymentId);
    }

    public function getLevel(): int
    {
        return Error::LEVEL_ERROR;
    }
}
