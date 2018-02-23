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

namespace Shopware\Context\Struct;

use Shopware\Api\Shop\Struct\ShopBasicStruct;
use Shopware\Defaults;
use Shopware\Framework\Struct\Struct;

class ShopContext extends Struct
{
    protected $languageId;

    /**
     * @var string
     */
    protected $fallbackLanguageId;

    /**
     * @var string
     */
    protected $versionId;

    /**
     * @var string
     */
    protected $applicationId;

    /**
     * @var array
     */
    protected $catalogIds;

    /**
     * @var string
     */
    protected $currencyId;

    /**
     * @var float
     */
    protected $currencyFactor;

    /**
     * @var array
     */
    protected $contextRules;

    public function __construct(string $applicationId, array $catalogIds, array $contextRules, string $currencyId, string $languageId, ?string $fallbackLanguageId = null, string $versionId = Defaults::LIVE_VERSION, float $currencyFactor = 1.0)
    {
        $this->languageId = $languageId;
        $this->fallbackLanguageId = $fallbackLanguageId;
        $this->versionId = $versionId;
        $this->applicationId = $applicationId;
        $this->catalogIds = $catalogIds;
        $this->currencyId = $currencyId;
        $this->currencyFactor = $currencyFactor;
        $this->contextRules = $contextRules;
    }

    public static function createDefaultContext(): self
    {
        return new self(Defaults::SHOP, [Defaults::CATALOG], [], Defaults::CURRENCY, Defaults::SHOP);
    }

    public static function createFromShop(ShopBasicStruct $shop): self
    {
        return new self(
            $shop->getId(),
            $shop->getCatalogIds(),
            [],
            $shop->getCurrency()->getId(),
            $shop->getLocaleId(),
            $shop->getFallbackTranslationId(),
            Defaults::LIVE_VERSION,
            $shop->getCurrency()->getFactor()
        );
    }

    public function hasFallback(): bool
    {
        return $this->getFallbackLanguageId() !== null
            && $this->getFallbackLanguageId() !== $this->getLanguageId();
    }

    public function getApplicationId(): string
    {
        return $this->applicationId;
    }

    public function getVersionId(): string
    {
        return $this->versionId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function getCatalogIds(): array
    {
        return $this->catalogIds;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function getCurrencyFactor(): float
    {
        return $this->currencyFactor;
    }

    public function getContextRules(): array
    {
        return $this->contextRules;
    }

    public function getFallbackLanguageId(): ? string
    {
        return $this->fallbackLanguageId;
    }

    public function createWithVersionId(string $versionId)
    {
        return new self(
            $this->applicationId,
            $this->catalogIds,
            $this->contextRules,
            $this->currencyId,
            $this->languageId,
            $this->fallbackLanguageId,
            $versionId,
            $this->currencyFactor
        );
    }
}
