import { MtCard } from '@shopware-ag/meteor-component-library';
import template from './mt-card.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for mt-card. Adds the component sections
 *  to the slots. Need to be matched with the original mt-card component.
 */
Shopware.Component.register('mt-card', {
    template,

    components: {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        'mt-card-original': MtCard,
    },

    props: {
        positionIdentifier: {
            type: String,
            required: true,
            default: null,
        },
    },

    computed: {},

    methods: {
        getFilteredSlots() {
            let allSlots: {
                [key: string]: unknown;
            } = {};

            allSlots = this.$slots;

            // Create a new object with the slots we want to keep as deleting is not possible because of read only protection
            const filteredSlots = Object.entries(allSlots).reduce(
                (
                    acc,
                    [
                        key,
                        value,
                    ],
                ) => {
                    if (key !== 'before-card' && key !== 'after-card') {
                        acc[key] = value;
                    }
                    return acc;
                },
                {} as { [key: string]: unknown },
            );

            return filteredSlots;
        },
    },
});
