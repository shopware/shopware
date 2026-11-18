/**
 * @sw-package fundamentals@framework
 */
import template from './sw-users-permissions-included-access-mark.html.twig';
import './sw-users-permissions-included-access-mark.scss';

/**
 * One cell of the included access matrix: a circle icon with the permissions that grant the access in its tooltip.
 *
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        /**
         * `full` and `partial` mark an area row, `muted` marks a data row inside an expanded area.
         */
        variant: {
            type: String as PropType<'full' | 'partial' | 'muted'>,
            required: true,
            validator(value: string): boolean {
                return [
                    'full',
                    'partial',
                    'muted',
                ].includes(value);
            },
        },

        /**
         * HTML content of the tooltip, with its labels already escaped.
         */
        tooltip: {
            type: String,
            required: true,
        },

        label: {
            type: String,
            required: true,
        },
    },

    computed: {
        icon(): string {
            return this.variant === 'partial' ? 'regular-minus-circle' : 'regular-check-circle';
        },
    },
});
