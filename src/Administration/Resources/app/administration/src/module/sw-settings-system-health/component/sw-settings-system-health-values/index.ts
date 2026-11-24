import template from './sw-settings-system-health-values.html.twig';
import './sw-settings-system-health-values.scss';

/**
 * @private
 */
export type SystemHealthValue = { key: string; label: string; value: string };

/**
 * Values as a grid of labels with their value underneath. A long value is cut off and shown in full on hover.
 *
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        items: {
            type: Array as PropType<SystemHealthValue[]>,
            required: true,
        },
    },
});
