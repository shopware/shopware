import './page/sw-settings-tax-list';
import './page/sw-settings-tax-detail';
import './component/sw-tax-rule-card';
import './component/sw-settings-tax-rule-modal';
import './component/sw-settings-tax-rule-type-individual-states';
import './component/sw-settings-tax-rule-type-zip-code';
import './component/sw-settings-tax-rule-type-zip-code-range';
import './component/sw-settings-tax-rule-type-individual-states-cell';
import './component/sw-settings-tax-rule-type-zip-code-cell';
import './component/sw-settings-tax-rule-type-zip-code-range-cell';

import './acl';

const { Module } = Shopware;

Module.register('sw-settings-tax', {
    type: 'core',
    name: 'settings-tax',
    title: 'sw-settings-tax.general.mainMenuItemGeneral',
    description: 'Tax section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'tax',

    routes: {
        index: {
            component: 'sw-settings-tax-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'tax.viewer',
            },
        },
        detail: {
            component: 'sw-settings-tax-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.tax.index',
                privilege: 'tax.viewer',
            },
            props: {
                default(route) {
                    return {
                        taxId: route.params.id,
                    };
                },
            },
        },
        create: {
            component: 'sw-settings-tax-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.tax.index',
                privilege: 'tax.creator',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.tax.index',
        icon: 'default-chart-pie',
        privilege: 'tax.viewer',
    },
});
