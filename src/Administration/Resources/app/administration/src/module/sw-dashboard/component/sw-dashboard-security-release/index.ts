import template from './sw-dashboard-security-release.html.twig';
import './sw-dashboard-security-release.scss';
import type { SecurityReleaseResponse } from 'src/core/service/api/update.api.service';

const { Component } = Shopware;

/**
 * @sw-package after-sales
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: ['updateService', 'acl'],

    data(): {
        securityRelease: SecurityReleaseResponse | null;
        isLoading: boolean;
    } {
        return {
            securityRelease: null,
            isLoading: false,
        };
    },

    computed: {
        isVisible(): boolean {
            return this.securityRelease?.version !== undefined
        },

        bannerVariant(): 'critical' | 'attention' {
            const severity = this.securityRelease?.highestSeverity ?? '';

            return severity === 'critical' || severity === 'high' ? 'critical' : 'attention';
        },

        title(): string {
            return this.$t('sw-dashboard.securityRelease.title', {
                version: this.securityRelease?.version ?? '',
            });
        },

        message(): string {
            return this.$t('sw-dashboard.securityRelease.message', {
                count: this.securityRelease?.totalVulnerabilities ?? 0,
                severity: this.securityRelease?.highestSeverity ?? '',
            });
        },

        releaseUrl(): string {
            return `https://github.com/shopware/shopware/releases/tag/v${this.securityRelease?.version ?? ''}`;
        },

        canUpdate(): boolean {
            return this.acl.can('system.core_update') && !Shopware.Context.app.hideUpdateModule;
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;

            try {
                this.securityRelease = await this.updateService.getSecurityRelease();
            } catch {
                this.securityRelease = null;
            } finally {
                this.isLoading = false;
            }
        },

        openWizard() {
            void this.$router.push({ name: 'sw.settings.shopware.updates.wizard' });
        },
    },
});
