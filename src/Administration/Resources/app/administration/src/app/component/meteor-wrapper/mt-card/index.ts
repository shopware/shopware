import { MtCard } from '@shopware-ag/meteor-component-library';
import template from './mt-card.html.twig';


// Use the compatConfig from the Shopware object and disable all compatibilities
// eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
MtCard.compatConfig = Object.fromEntries(Object.keys(Shopware.compatConfig).map(key => [key, false]));

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for mt-card. Adds the component sections
 *  to the slots. Need to be matched with the original mt-card component.
 */
Shopware.Component.register('mt-card', {
    template,

    compatConfig: Shopware.compatConfig,

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

    computed: {
        listeners() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
        },
    },

    methods: {
        getFilteredSlots() {
            let allSlots: {
                [key: string]: unknown;
            } = {};

            if (this.isCompatEnabled('INSTANCE_SCOPED_SLOTS')) {
                allSlots = {
                    ...this.$slots,
                    ...this.$scopedSlots,
                };
            } else {
                allSlots = this.$slots;
            }

            // Remove already used slots
            delete allSlots['before-card'];
            delete allSlots['after-card'];

            return allSlots;
        },
    },
});
