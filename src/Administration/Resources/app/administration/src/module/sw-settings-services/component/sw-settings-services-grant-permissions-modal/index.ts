/**
 * @sw-package framework
 */
import { MtModalAction, MtModalClose } from '@shopware-ag/meteor-component-library';
import template from './sw-settings-services-grant-permissions-modal.html.twig'
import './sw-settings-services-grant-permissions-modal.scss';
import grantPermissionsBackground from './assets/grant-permissions-background.png';
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
            feedbackLink: '',
        };
    },

    computed: {
        showGrantPermissionsModal: {
            get() {
                return useShopwareServicesStore().showGrantPermissionsModal;
            },
            set(value: boolean) {
                useShopwareServicesStore().showGrantPermissionsModal = value;
            },
        },
    },

    created() {
        Shopware.Service('shopwareServicesService')
            .getLegalDocumentLinks()
            .then(({ feedbackLink }) => {
                this.feedbackLink = feedbackLink;
            })
            .catch(() => {});
    },

    methods: {
        grantPermissions(done: () => void) {
            console.log('Grant permissions');

            done();
        },
    },
});