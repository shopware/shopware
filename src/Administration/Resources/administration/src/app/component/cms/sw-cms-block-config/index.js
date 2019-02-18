import type from 'src/core/service/utils/types.utils';
import template from './sw-cms-block-config.html.twig';

export default {
    name: 'sw-cms-block-config',
    template,

    props: {
        config: {
            type: Object,
            required: true
        },
        block: {
            type: Object, // requires a block entity with loaded slots
            required: true
        }
    },

    computed: {
        slots() {
            return Object.values(this.block.getAssociation('slots').store);
        }
    },

    methods: {
        resolveSlotType(cmsSlot) {
            return `sw-cms-${cmsSlot.type}-form`;
        },

        slotConfig(slot) {
            if (!type.isPlainObject(this.config[slot.id])) {
                this.$set(this.config, slot.id, {});
            }

            return this.config[slot.id];
        }
    }
};
