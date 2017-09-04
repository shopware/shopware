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

namespace Shopware\Area\Event;

use Shopware\Area\Struct\AreaDetailCollection;
use Shopware\AreaCountry\Event\AreaCountryBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class AreaDetailLoadedEvent extends NestedEvent
{
    const NAME = 'area.detail.loaded';

    /**
     * @var AreaDetailCollection
     */
    protected $areas;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(AreaDetailCollection $areas, TranslationContext $context)
    {
        $this->areas = $areas;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getAreas(): AreaDetailCollection
    {
        return $this->areas;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection(
            [
                new AreaBasicLoadedEvent($this->areas, $this->context),
                new AreaCountryBasicLoadedEvent($this->areas->getAreaCountries(), $this->context),
            ]
        );
    }
}
