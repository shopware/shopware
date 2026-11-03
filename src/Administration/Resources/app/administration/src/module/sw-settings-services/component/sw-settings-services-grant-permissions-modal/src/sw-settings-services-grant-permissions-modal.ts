/**
 * @sw-package framework
 */
import template from './sw-settings-services-grant-permissions-modal.html.twig';
import './sw-settings-services-grant-permissions-modal.scss';
import {
    extractErrorMessage,
    getAssetFilter,
    grantPermissions,
    useNotificationStore,
    useSession,
    useShopwareServicesStore,
    wrapComponentConfig,
} from '../dependencies';

/**
 * @private
 */
export default wrapComponentConfig({
    name: 'sw-settings-services-grant-permissions-modal',
    template,

    data() {
        const assetFilter = getAssetFilter();

        return {
            grantPermissionsBackground: assetFilter(
                '/administration/administration/static/img/services/grant-permissions-background.svg',
            ),
            isLoading: false,
        };
    },

    computed: {
        feedbackLink() {
            return useShopwareServicesStore().currentRevision?.links['docs-url'] ?? '';
        },

        showGrantPermissionsModal: {
            get() {
                return useShopwareServicesStore().showGrantPermissionsModal;
            },
            set(value: boolean) {
                useShopwareServicesStore().showGrantPermissionsModal = value;
            },
        },
    },

    methods: {
        prepareRevisions(isOpen: boolean) {
            this.showGrantPermissionsModal = isOpen;

            if (this.showGrantPermissionsModal && !this.feedbackLink) {
                Shopware.Service('serviceRegistryClient')
                    .getCurrentRevision(useSession().currentLocale.value as string)
                    .then((revisions) => {
                        useShopwareServicesStore().revisions = revisions;
                    })
                    .catch(() => {});
            }
        },

        async grantPermissions(done: () => void) {
            try {
                this.isLoading = true;

                await grantPermissions();
            } catch (exception) {
                useNotificationStore().createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: extractErrorMessage(exception),
                });
            } finally {
                this.isLoading = false;
                done();
            }
        },
    },
});
