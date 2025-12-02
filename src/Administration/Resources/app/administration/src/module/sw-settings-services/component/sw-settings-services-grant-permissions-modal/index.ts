/**
 * @sw-package framework
 */
import { MtModal, MtModalRoot, MtModalAction, MtModalClose } from '@shopware-ag/meteor-component-library';
import useSession from 'src/app/composables/use-session';
import template from './sw-settings-services-grant-permissions-modal.html.twig';
import './sw-settings-services-grant-permissions-modal.scss';
import { useShopwareServicesStore } from '../../store/shopware-services.store';
import extractErrorMessage from '../../composables/extract-error';
import { grantPermissions } from '../../composables/permissions';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-grant-permissions-modal',
    template,

    components: {
        MtModal,
        MtModalRoot,
        MtModalAction,
        MtModalClose,
    },

    data() {
        const assetFilter = Shopware.Filter.getByName('asset');

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
                Shopware.Store.get('notification').createNotification({
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
