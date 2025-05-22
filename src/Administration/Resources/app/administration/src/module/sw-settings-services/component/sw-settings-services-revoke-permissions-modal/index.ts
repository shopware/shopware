import type { PropType } from 'vue';
import {
    MtModalTrigger,
    MtModalAction,
    MtModalClose,
} from '@shopware-ag/meteor-component-library';
import template from './sw-settings-services-revoke-permissions-modal.html.twig';
import './sw-settings-services-revoke-permissions-modal.scss';
import type { ServiceDescription } from '../../service/shopware-services.service';

/**
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-revoke-permissions-modal',
    template,

    components: {
        MtModalAction,
        MtModalTrigger,
        MtModalClose,
    },

    props: {
        optionalServices: {
            type: Array as PropType<ServiceDescription[]>,
        },
    },

    methods: {
        revokePermissions(done: () => void) {
            console.log('Revoke permissions');

            done();
        },
    },
})