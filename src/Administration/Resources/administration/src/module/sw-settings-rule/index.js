import './extension/sw-settings-index';
import './page/sw-settings-rule-list';
import './page/sw-settings-rule-detail';
import './page/sw-settings-rule-create';
import './component/sw-condition-not-found';
import './component/sw-condition-operator-select';
import './component/sw-condition-modal';
import './component/sw-condition-billing-country';
import './component/sw-condition-billing-street';
import './component/sw-condition-billing-zip-code';
import './component/sw-condition-cart-amount';
import './component/sw-condition-cart-has-delivery-free-item';
import './component/sw-condition-currency';
import './component/sw-condition-line-items-in-cart-count';
import './component/sw-condition-customer-group';
import './component/sw-condition-customer-number';
import './component/sw-condition-date-range';
import './component/sw-condition-day-of-week';
import './component/sw-condition-days-since-last-order';
import './component/sw-condition-different-addresses';
import './component/sw-condition-goods-count';
import './component/sw-condition-goods-price';
import './component/sw-condition-is-new-customer';
import './component/sw-condition-last-name';
import './component/sw-condition-is-company';
import './component/sw-condition-line-item';
import './component/sw-condition-line-item-of-type';
import './component/sw-condition-line-item-total-price';
import './component/sw-condition-line-item-unit-price';
import './component/sw-condition-line-item-with-quantity';
import './component/sw-condition-line-items-in-cart';
import './component/sw-condition-order-count';
import './component/sw-condition-sales-channel';
import './component/sw-condition-shipping-country';
import './component/sw-condition-shipping-street';
import './component/sw-condition-shipping-zip-code';
import './component/sw-condition-time-range';
import './component/sw-condition-weight-of-cart';
import './component/sw-condition-line-item-tag';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-settings-rule', {
    type: 'core',
    name: 'settings-rule',
    title: 'sw-settings-rule.general.mainMenuItemGeneral',
    description: 'sw-settings-rule.general.descriptionTextModule',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'rule',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-rule-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-rule-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.rule.index'
            }
        },
        create: {
            component: 'sw-settings-rule-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.rule.index'
            }
        }
    }
});
