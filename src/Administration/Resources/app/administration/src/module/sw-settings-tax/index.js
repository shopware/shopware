import './acl';
import './component/sw-settings-tax-provider-sorting-modal/register';
import './component/sw-settings-tax-rule-modal/register';
import './component/sw-settings-tax-rule-type-individual-states/register';
import './component/sw-settings-tax-rule-type-individual-states-cell/register';
import './component/sw-settings-tax-rule-type-zip-code/register';
import './component/sw-settings-tax-rule-type-zip-code-cell/register';
import './component/sw-settings-tax-rule-type-zip-code-range/register';
import './component/sw-settings-tax-rule-type-zip-code-range-cell/register';
import './component/sw-tax-rule-card/register';
import './page/sw-settings-tax-detail/register';
import './page/sw-settings-tax-list/register';
import './page/sw-settings-tax-provider-detail/register';

/* eslint-enable sw-deprecation-rules/private-feature-declarations */

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-tax', {
    type: 'core',
    name: 'settings-tax',
    title: 'sw-settings-tax.general.mainMenuItemGeneral',
    description: 'Tax section in the settings module',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-chart-pie',
    favicon: 'icon-module-settings.svg',
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
                        taxId: route.params.id.toLowerCase(),
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
        'tax_provider.detail': {
            component: 'sw-settings-tax-provider-detail',
            path: 'tax-provider/detail/:id',
            meta: {
                parentPath: 'sw.settings.tax.index',
                privilege: 'tax.viewer',
            },
            props: {
                default(route) {
                    return {
                        taxProviderId: route.params.id.toLowerCase(),
                    };
                },
            },
        },
    },

    settingsItem: {
        group: 'localization',
        to: 'sw.settings.tax.index',
        icon: 'regular-chart-pie',
        privilege: 'tax.viewer',
    },
});
