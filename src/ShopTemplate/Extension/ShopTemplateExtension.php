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

namespace Shopware\ShopTemplate\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\DetailFactoryExtensionInterface;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\ShopTemplate\Event\ShopTemplateBasicLoadedEvent;
use Shopware\ShopTemplate\Event\ShopTemplateWrittenEvent;
use Shopware\ShopTemplate\Struct\ShopTemplateBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ShopTemplateExtension implements DetailFactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ShopTemplateBasicLoadedEvent::NAME => 'shopTemplateBasicLoaded',
            ShopTemplateWrittenEvent::NAME => 'shopTemplateWritten',
        ];
    }

    public function joinDependencies(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
    }

    public function getBasicFields(): array
    {
        return [];
    }

    public function hydrate(
        ShopTemplateBasicStruct $shopTemplate,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function shopTemplateBasicLoaded(ShopTemplateBasicLoadedEvent $event): void
    {
    }

    public function shopTemplateWritten(ShopTemplateWrittenEvent $event): void
    {
    }
}
