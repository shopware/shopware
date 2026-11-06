import { ref } from 'vue';
import type { NpsResponse } from 'src/app/component/structure/sw-admin-nps-card';
import template from './sw-admin-nps-card-data-provider.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @description Decides whether the NPS card is shown and handles its events.
 * @status prototype
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    setup() {
        // Hidden until eligibility is implemented.
        const isVisible = ref(false);

        // Temporary stand-ins for the NPS API requests while the backend is not available yet.
        const onSubmit = (response: NpsResponse) => {
            // eslint-disable-next-line no-console -- Placeholder for the real API request.
            console.log('[NPS] submit', response);
        };

        const onNotNow = (response: NpsResponse) => {
            // eslint-disable-next-line no-console -- Placeholder for the real API request.
            console.log('[NPS] not now', response);
        };

        const onClose = () => {
            // eslint-disable-next-line no-console -- Placeholder for the real API request.
            console.log('[NPS] close');
        };

        return {
            isVisible,
            onSubmit,
            onNotNow,
            onClose,
        };
    },
});
