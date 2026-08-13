import template from './global-alias-component.html.twig';

const { Context } = Shopware;
const { Criteria } = Shopware.Data;
const utils = Shopware.Utils;
const { types } = utils;

Shopware.Component.register('sw-global-alias-card', {
    template,

    props: {
        criteria: {
            type: Criteria,
            required: false,
            default: null,
        },
        esEnabled: {
            type: Boolean,
            required: false,
            default: Context.app.adminEsEnable ?? false,
        },
    },

    emits: {
        save: (payload) => types.isObject(payload),
    },

    methods: {
        save(payload) {
            this.$emit('save', payload);
        },
    },
});
