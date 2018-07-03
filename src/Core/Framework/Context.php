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

namespace Shopware\Core\Framework;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Touchpoint\TouchpointStruct;

class Context extends Struct
{
    /**
     * @var string
     */
    protected $tenantId;

    /**
     * @var string
     */
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
    protected $touchpointId;

    /**
     * @var array|null
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
    protected $rules;

    public function __construct(
        string $tenantId,
        string $touchpointId,
        ?array $catalogIds,
        array $rules,
        string $currencyId,
        string $languageId,
        ?string $fallbackLanguageId = null,
        string $versionId = Defaults::LIVE_VERSION,
        float $currencyFactor = 1.0
    ) {
        $this->tenantId = $tenantId;
        $this->touchpointId = $touchpointId;
        $this->catalogIds = $catalogIds;
        $this->rules = $rules;
        $this->currencyId = $currencyId;
        $this->languageId = $languageId;
        $this->fallbackLanguageId = $fallbackLanguageId;
        $this->versionId = $versionId;
        $this->currencyFactor = $currencyFactor;

        $this->addExtension('write_protection', new ArrayStruct());
    }

    public static function createDefaultContext(string $tenantId): self
    {
        return new self($tenantId, Defaults::TOUCHPOINT, [Defaults::CATALOG], [], Defaults::CURRENCY, Defaults::LANGUAGE);
    }

    public static function createFromTouchpoint(TouchpointStruct $touchpoint): self
    {
        return new self(
            $touchpoint->getTenantId(),
            $touchpoint->getId(),
            $touchpoint->getCatalogIds(),
            [],
            $touchpoint->getCurrencyId(),
            $touchpoint->getLanguageId(),
            $touchpoint->getLanguage()->getParentId(),
            Defaults::LIVE_VERSION,
            $touchpoint->getCurrency()->getFactor()
        );
    }

    public function hasFallback(): bool
    {
        return $this->getFallbackLanguageId() !== null
            && $this->getFallbackLanguageId() !== $this->getLanguageId();
    }

    public function getTouchpointId(): string
    {
        return $this->touchpointId;
    }

    public function getVersionId(): string
    {
        return $this->versionId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function getCatalogIds(): ?array
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

    public function getRules(): array
    {
        return $this->rules;
    }

    public function getFallbackLanguageId(): ?string
    {
        return $this->fallbackLanguageId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function createWithVersionId(string $versionId): self
    {
        return new self(
            $this->tenantId,
            $this->touchpointId,
            $this->catalogIds,
            $this->rules,
            $this->currencyId,
            $this->languageId,
            $this->fallbackLanguageId,
            $versionId,
            $this->currencyFactor
        );
    }

    public function createWithCatalogIds(array $catalogIds): self
    {
        return new self(
            $this->tenantId,
            $this->touchpointId,
            $catalogIds,
            $this->rules,
            $this->currencyId,
            $this->languageId,
            $this->fallbackLanguageId,
            $this->versionId,
            $this->currencyFactor
        );
    }
}
