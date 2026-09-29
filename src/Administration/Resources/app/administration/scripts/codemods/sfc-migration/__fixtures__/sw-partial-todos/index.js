import template from './sw-partial-todos.html.twig';

export default {
    template,

    inject: {
        feature: {
            from: 'feature',
            default: null,
        },
    },

    metaInfo() {
        return {
            title: this.title,
        };
    },

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
    },

    data() {
        return {
            title: 'Partial',
        };
    },

    methods: {
        onSave() {
            if (this.$device.getViewportWidth() > 500) {
                this.title = 'Saved';
            }
        },
    },
};
