import template from './sw-flow-detail.html.twig';

const { Component, Mixin, Context } = Shopware;

Component.register('sw-flow-detail', {
    template,

    inject: [
        'acl',
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
    ],

    props: {
        flowId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            flow: {},
        };
    },

    computed: {
        flowRepository() {
            return this.repositoryFactory.create('flow');
        },

        isNewFlow() {
            return !this.flowId;
        },
    },

    watch: {
        flowId() {
            this.getDetailFlow();
        },
    },

    created() {
        this.createComponent();
    },

    methods: {
        createComponent() {
            if (this.flowId) {
                this.getDetailFlow();
                return;
            }

            this.createNewFlow();
        },

        routeDetailTab(tabName) {
            if (!tabName) return '';

            if (this.isNewFlow) {
                return `sw.flow.create.${tabName}`;
            }

            return `sw.flow.detail.${tabName}`;
        },

        createNewFlow() {
            this.flow = this.flowRepository.create(Context.api);
            this.flow.priority = 0;
        },

        getDetailFlow() {
            this.isLoading = true;
            this.flowRepository.get(this.flowId)
                .then((data) => {
                    this.flow = data;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-flow.flowNotification.messageError'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onSave() {
            if (!this.flow.eventName) {
                this.createNotificationWarning({
                    message: this.$tc('sw-flow.flowNotification.messageRequiredEventName'),
                });

                return;
            }

            this.isSaveSuccessful = false;
            this.isLoading = true;
            this.flowRepository.save(this.flow)
                .then(() => {
                    this.isSaveSuccessful = true;
                    this.createNotificationSuccess({
                        message: this.$tc('sw-flow.flowNotification.messageSaveSuccess'),
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-flow.flowNotification.messageSaveError'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        saveFinish() {
            this.isLoading = false;
            this.isSaveSuccessful = false;

            this.$router.push({
                name: 'sw.flow.detail',
                params: { id: this.flow.id },
            });
        },
    },
});
