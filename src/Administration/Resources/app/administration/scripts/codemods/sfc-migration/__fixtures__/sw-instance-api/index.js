import template from './sw-instance-api.html.twig';

export default {
    template,

    emits: ['modal-close'],

    data() {
        return {
            active: false,
        };
    },

    methods: {
        openModal() {
            this.active = true;

            this.$nextTick(() => {
                this.$refs.modalContent.focus();
            });
        },

        closeModal() {
            this.active = false;
            this.$emit('modal-close');

            if (this.$props.active === undefined) {
                this.$router.push({ name: 'sw.dashboard.index', params: this.$route.params });
            }
        },

        successMessage() {
            return this.$t('global.default.success');
        },
    },
};
