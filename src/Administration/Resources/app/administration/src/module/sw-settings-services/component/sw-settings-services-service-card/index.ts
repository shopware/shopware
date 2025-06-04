/**
 * @sw-package framework
 */
import type { PropType } from 'vue';
import type { ServiceDescription } from '../../service/shopware-services.service';
import template from './sw-settings-services-service-card.html.twig';
import './sw-settings-services-service-card.scss';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-service-card',

    template,

    props: {
        service: {
            required: true,
            type: Object as PropType<ServiceDescription>,
        },
    },

    computed: {
        serviceStatus() {
            if (!this.service.active) {
                return 'red';
            }

            return this.service.requestedPermissions.length === 0 ? 'green' : 'orange';
        },

        statusText() {
            switch (this.serviceStatus) {
                case 'green': return 'sw-settings-services.service-card.statusActive';
                case 'orange': return 'sw-settings-services.service-card.statusAwaitingPermissions';
                case 'red':
                default:
                    return 'sw-settings-services.service-card.statusInactive';
            }
        },
    },
})