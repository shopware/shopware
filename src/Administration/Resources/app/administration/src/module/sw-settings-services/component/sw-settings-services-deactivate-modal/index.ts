import {
    MtModalTrigger,
    MtModalAction,
    MtModalClose,
} from '@shopware-ag/meteor-component-library';
import template from './sw-settings-services-deactivate-modal.html.twig';
import './sw-settings-services-deactivate-modal.scss';

/**
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-deactivate-modal',
    template,

    components: {
        MtModalAction,
        MtModalTrigger,
        MtModalClose,
    },

    props: {
        feedbackLink: {
            type: String,
        },
    },

    methods: {
        revokePermissions(done: () => void) {
            console.log('Deactivate services');

            done();
        },
    },
})