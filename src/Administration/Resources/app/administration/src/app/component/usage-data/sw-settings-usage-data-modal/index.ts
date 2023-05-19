import { ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY } from 'src/core/service/api/metrics.api.service';
import template from './sw-settings-usage-data-modal.html.twig';
import './sw-settings-usage-data-modal.scss';

const { Component } = Shopware;

/**
 * @private
 *
 * @package merchant-services
 */
Component.register('sw-settings-usage-data-modal', {
    template,

    inject: [
        'acl',
        'systemConfigApiService',
        'loginService',
        'metricsService',
    ],

    data() {
        return {
            isVisible: false,
        };
    },

    computed: {
        currentUser() {
            return Shopware.State.get('session').currentUser;
        },
    },

    watch: {
        currentUser: {
            handler() {
                void this.shouldModalBeVisible().then((result: boolean) => {
                    this.isVisible = result;
                });
            },
            immediate: true,
        },
    },

    methods: {
        async saveSystemConfig(value: boolean) {
            await this.systemConfigApiService.saveValues({
                [ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY]: value,
            });

            this.isVisible = false;
        },

        async shouldModalBeVisible(): Promise<boolean> {
            if (!this.loginService.isLoggedIn()) {
                return false;
            }

            if (!this.acl.isAdmin()) {
                return false;
            }

            return this.metricsService.needsApproval();
        },
    },
});
