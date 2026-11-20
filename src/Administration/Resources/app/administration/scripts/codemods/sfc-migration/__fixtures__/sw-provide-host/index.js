import { computed } from 'vue';
import template from './sw-provide-host.html.twig';

export default {
    template,

    props: {
        entity: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            openedId: null,
        };
    },

    provide() {
        return {
            getEntity: computed(() => this.entity),
            registerItem: this.registerItem,
            initialOpenedId: this.openedId,
        };
    },

    methods: {
        registerItem(id) {
            this.openedId = id;
        },
    },
};
