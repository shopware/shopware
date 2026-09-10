import template from './sw-lifecycle-demo.html.twig';

export default {
    template,

    data() {
        return {
            observer: null,
            entries: [],
        };
    },

    async created() {
        await this.fetchEntries();
    },

    mounted() {
        this.observer = 'attached';
    },

    beforeDestroy() {
        this.observer = null;
    },

    methods: {
        async fetchEntries() {
            this.entries = [];
        },
    },
};
