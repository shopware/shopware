<?php
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

namespace Shopware\Currency\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Event\CurrencyBasicLoadedEvent;
use Shopware\Currency\Event\CurrencyDetailLoadedEvent;
use Shopware\Currency\Event\CurrencyWrittenEvent;
use Shopware\Currency\Struct\CurrencyBasicStruct;
use Shopware\Framework\Factory\DetailFactoryExtensionInterface;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class CurrencyExtension implements DetailFactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            CurrencyBasicLoadedEvent::NAME => 'currencyBasicLoaded',
            CurrencyDetailLoadedEvent::NAME => 'currencyDetailLoaded',
            CurrencyWrittenEvent::NAME => 'currencyWritten',
        ];
    }

    public function joinDependencies(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
    }

    public function getDetailFields(): array
    {
        return [];
    }

    public function getBasicFields(): array
    {
        return [];
    }

    public function hydrate(
        CurrencyBasicStruct $currency,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function currencyBasicLoaded(CurrencyBasicLoadedEvent $event): void
    {
    }

    public function currencyDetailLoaded(CurrencyDetailLoadedEvent $event): void
    {
    }

    public function currencyWritten(CurrencyWrittenEvent $event): void
    {
    }
}
