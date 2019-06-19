import { Module } from 'src/core/shopware';
import './component/sw-first-run-wizard-modal';
import './component/sw-plugin-card';
import './page/index';
import './view/sw-first-run-wizard-welcome';
import './view/sw-first-run-wizard-demodata';
import './view/sw-first-run-wizard-paypal-base';
import './view/sw-first-run-wizard-paypal-info';
import './view/sw-first-run-wizard-paypal-install';
import './view/sw-first-run-wizard-paypal-credentials';
import './view/sw-first-run-wizard-plugins';
import './view/sw-first-run-wizard-shopware-base';
import './view/sw-first-run-wizard-shopware-account';
import './view/sw-first-run-wizard-shopware-domain';
import './view/sw-first-run-wizard-finish';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Module.register('sw-first-run-wizard', {
    type: 'core',
    name: 'first-run-wizard',
    title: 'sw-login.general.mainMenuItemsGeneral',
    description: 'First Run Wizard to set up languages and plugins after the installation process',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#F19D12',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-first-run-wizard',
            path: 'index',
            redirect: {
                name: 'sw.first.run.wizard.index.welcome'
            },
            children: {
                welcome: {
                    component: 'sw-first-run-wizard-welcome',
                    path: ''
                },
                demodata: {
                    component: 'sw-first-run-wizard-demodata',
                    path: 'demodata'
                },
                paypal: {
                    component: 'sw-first-run-wizard-paypal-base',
                    path: 'paypal',
                    children: {
                        info: {
                            component: 'sw-first-run-wizard-paypal-info',
                            path: ''
                        },
                        install: {
                            component: 'sw-first-run-wizard-paypal-install',
                            path: 'install'
                        },
                        credentials: {
                            component: 'sw-first-run-wizard-paypal-credentials',
                            path: 'credentials'
                        }
                    }
                },
                plugins: {
                    component: 'sw-first-run-wizard-plugins',
                    path: 'plugins'
                },
                shopware: {
                    component: 'sw-first-run-wizard-shopware-base',
                    path: 'shopware',
                    children: {
                        info: {
                            component: 'sw-first-run-wizard-shopware-account',
                            path: ''
                        },
                        install: {
                            component: 'sw-first-run-wizard-shopware-domain',
                            path: 'domain'
                        }
                    }
                },
                finish: {
                    component: 'sw-first-run-wizard-finish',
                    path: 'finish'
                }
            }
        }
    }
});
