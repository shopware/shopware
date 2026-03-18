import template from './simple-component.html.twig';

Shopware.Component.register('sw-simple-card', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            title: 'Default Title',
            isLoading: false,
        };
    },

    computed: {
        description() {
            return `This is: ${this.title}`;
        },
    },

    methods: {
        onSave() {
            this.isLoading = true;
            this.$emit('save', this.title);
        },
    },
});
