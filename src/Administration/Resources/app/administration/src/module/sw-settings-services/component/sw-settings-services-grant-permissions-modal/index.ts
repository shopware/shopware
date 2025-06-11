/**
 * @sw-package framework
 */
import { MtModalAction, MtModalClose } from '@shopware-ag/meteor-component-library';
import useSession from 'src/app/composables/use-session';
import template from './sw-settings-services-grant-permissions-modal.html.twig'
import './sw-settings-services-grant-permissions-modal.scss';
// eslint-disable-next-line import/no-unresolved
import grantPermissionsBackground from './assets/grant-permissions-background.svg?no-inline';
import { useShopwareServicesStore} from '../../store/shopware-services.store';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-grant-permissions-modal',
    template,

    components: {
        MtModalAction,
        MtModalClose,
    },

    data() {
        return {
            grantPermissionsBackground,
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
                Shopware.Service('serviceRegistryClient').getCurrentRevision(useSession().currentLocale.value as string)
                    .then((revisions) => {
                        useShopwareServicesStore().revisions = revisions;
                    })
                    .catch(() => {});
            }
        },

        grantPermissions(done: () => void) {
            console.log('Grant permissions');

            done();
        },
    },
});