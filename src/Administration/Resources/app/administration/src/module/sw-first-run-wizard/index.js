import './component/sw-first-run-wizard-modal/register';
import './component/sw-plugin-card/register';
import './view/sw-first-run-wizard-data-import/register';
import './view/sw-first-run-wizard-defaults/register';
import './view/sw-first-run-wizard-finish/register';
import './view/sw-first-run-wizard-mailer-base/register';
import './view/sw-first-run-wizard-mailer-local/register';
import './view/sw-first-run-wizard-mailer-selection/register';
import './view/sw-first-run-wizard-mailer-smtp/register';
import './view/sw-first-run-wizard-paypal-base/register';
import './view/sw-first-run-wizard-paypal-credentials/register';
import './view/sw-first-run-wizard-paypal-info/register';
import './view/sw-first-run-wizard-plugins/register';
import './view/sw-first-run-wizard-shopware-account/register';
import './view/sw-first-run-wizard-shopware-base/register';
import './view/sw-first-run-wizard-shopware-domain/register';
import './view/sw-first-run-wizard-store/register';
import './view/sw-first-run-wizard-welcome/register';
const { Module } = Shopware;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-first-run-wizard', () => import('./page/index'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

/**
 * @sw-package fundamentals@after-sales
 *
 * @private
 */
Module.register('sw-first-run-wizard', {
    type: 'core',
    name: 'first-run-wizard',
    title: 'sw-first-run-wizard.general.mainMenuItemGeneral',
    description: 'First Run Wizard to set up languages and plugins after the installation process',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#F19D12',

    routes: {
        index: {
            component: 'sw-first-run-wizard',
            path: 'index',
            meta: {
                privilege: 'admin',
            },
            redirect: {
                name: 'sw.first.run.wizard.index.welcome',
            },
            children: {
                welcome: {
                    component: 'sw-first-run-wizard-welcome',
                    path: '',
                    meta: {
                        privilege: 'admin',
                    },
                },
                'data-import': {
                    component: 'sw-first-run-wizard-data-import',
                    path: 'data-import',
                    meta: {
                        privilege: 'admin',
                    },
                },
                mailer: {
                    component: 'sw-first-run-wizard-mailer-base',
                    path: 'mailer',
                    meta: {
                        privilege: 'admin',
                    },
                    children: {
                        selection: {
                            component: 'sw-first-run-wizard-mailer-selection',
                            path: 'selection',
                            meta: {
                                privilege: 'admin',
                            },
                        },
                        smtp: {
                            component: 'sw-first-run-wizard-mailer-smtp',
                            path: 'smtp',
                            meta: {
                                privilege: 'admin',
                            },
                        },
                        local: {
                            component: 'sw-first-run-wizard-mailer-local',
                            path: 'local',
                            meta: {
                                privilege: 'admin',
                            },
                        },
                    },
                },
                paypal: {
                    component: 'sw-first-run-wizard-paypal-base',
                    path: 'paypal',
                    meta: {
                        privilege: 'admin',
                    },
                    children: {
                        info: {
                            component: 'sw-first-run-wizard-paypal-info',
                            path: 'info',
                            meta: {
                                privilege: 'admin',
                            },
                        },
                        install: {
                            component: 'sw-first-run-wizard-paypal-install',
                            path: 'install',
                            meta: {
                                privilege: 'admin',
                            },
                        },
                        credentials: {
                            component: 'sw-first-run-wizard-paypal-credentials',
                            path: 'credentials',
                            meta: {
                                privilege: 'admin',
                            },
                        },
                    },
                },
                plugins: {
                    component: 'sw-first-run-wizard-plugins',
                    path: 'plugins',
                    meta: {
                        privilege: 'admin',
                    },
                },
                shopware: {
                    component: 'sw-first-run-wizard-shopware-base',
                    path: 'shopware',
                    children: {
                        account: {
                            component: 'sw-first-run-wizard-shopware-account',
                            path: 'account',
                            meta: {
                                privilege: 'admin',
                            },
                        },
                        domain: {
                            component: 'sw-first-run-wizard-shopware-domain',
                            path: 'domain',
                            meta: {
                                privilege: 'admin',
                            },
                        },
                    },
                },
                store: {
                    component: 'sw-first-run-wizard-store',
                    path: 'store',
                    meta: {
                        privilege: 'admin',
                    },
                },
                defaults: {
                    component: 'sw-first-run-wizard-defaults',
                    path: 'defaults',
                    meta: {
                        privilege: 'admin',
                    },
                },
                finish: {
                    component: 'sw-first-run-wizard-finish',
                    path: 'finish',
                    meta: {
                        privilege: 'admin',
                    },
                },
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.first.run.wizard.index',
        icon: 'regular-rocket',
        privilege: 'admin',
    },
});
