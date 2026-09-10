/**
 * @sw-package framework
 */
import template from './sw-integration-tabs.html.twig';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,
    inject: ['acl'],

    computed: {
        tabs() {
            return [
                {
                    name: 'sw.integration.index',
                    label: this.$t('sw-integration.general.headlineIntegrations'),
                    privilege: 'integration.viewer',
                },
                {
                    name: 'sw.integration.oauth',
                    label: this.$t('sw-oauth-client.title'),
                    privilege: 'oauth_client.viewer',
                },
            ]
                .filter((tab) => this.acl.can(tab.privilege))
                .map((tab) => ({
                    name: tab.name,
                    label: tab.label,
                    onClick: () => void this.$router.push({ name: tab.name }),
                }));
        },
    },
});
