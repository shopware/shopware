import './extension/sw-settings-index';
import './component/sw-first-run-wizard-modal';
import './component/sw-plugin-card';
import './page/index';
import './view/sw-first-run-wizard-welcome';
import './view/sw-first-run-wizard-data-import';
import './view/sw-first-run-wizard-mailer-base';
import './view/sw-first-run-wizard-mailer-selection';
import './view/sw-first-run-wizard-mailer-smtp';
import './view/sw-first-run-wizard-paypal-base';
import './view/sw-first-run-wizard-paypal-info';
import './view/sw-first-run-wizard-paypal-credentials';
import './view/sw-first-run-wizard-plugins';
import './view/sw-first-run-wizard-shopware-base';
import './view/sw-first-run-wizard-shopware-account';
import './view/sw-first-run-wizard-shopware-domain';
import './view/sw-first-run-wizard-finish';

const { Module } = Shopware;

Module.register('sw-first-run-wizard', {
    type: 'core',
    name: 'first-run-wizard',
    title: 'sw-login.general.mainMenuItemsGeneral',
    description: 'First Run Wizard to set up languages and plugins after the installation process',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#F19D12',

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
                'data-import': {
                    component: 'sw-first-run-wizard-data-import',
                    path: 'data-import'
                },
                mailer: {
                    component: 'sw-first-run-wizard-mailer-base',
                    path: 'mailer',
                    children: {
                        selection: {
                            component: 'sw-first-run-wizard-mailer-selection',
                            path: 'selection'
                        },
                        smtp: {
                            component: 'sw-first-run-wizard-mailer-smtp',
                            path: 'smtp'
                        }
                    }
                },
                paypal: {
                    component: 'sw-first-run-wizard-paypal-base',
                    path: 'paypal',
                    children: {
                        info: {
                            component: 'sw-first-run-wizard-paypal-info',
                            path: 'info'
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
                        account: {
                            component: 'sw-first-run-wizard-shopware-account',
                            path: 'account'
                        },
                        domain: {
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
