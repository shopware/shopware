import template from './sw-empty-state.html.twig';
import './sw-empty-state.scss';

/**
 * @private
 */
export default {
    name: 'sw-empty-state',
    template,

    props: {
        title: {
            type: String,
            default: '',
            required: true
        },
        subline: {
            type: String,
            default: '',
            required: false
        }
    },

    computed: {
        moduleColor() {
            return this.$route.meta.$module.color;
        },

        moduleDescription() {
            return this.subline || this.$route.meta.$module.description;
        },

        moduleIcon() {
            return this.$route.meta.$module.icon;
        },

        hasActionSlot() {
            return !!this.$slots.actions;
        }
    }
};
