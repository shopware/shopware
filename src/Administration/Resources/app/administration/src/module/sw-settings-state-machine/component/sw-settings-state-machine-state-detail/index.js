import template from './sw-settings-state-machine-state-detail.html.twig';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

/**
 * @sw-package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'repositoryFactory',
    ],

    emits: ['modal-close'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        currentStateMachineState: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            stateMachineState: null,
        };
    },

    computed: {
        stateMachineStateRepository() {
            return this.repositoryFactory.create('state_machine_state');
        },

        ...mapPropertyErrors('stateMachineState', [
            'name',
        ]),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.stateMachineState = this.currentStateMachineState;
        },

        onCancel() {
            this.$emit('modal-close');
        },

        async onSave() {
            try {
                await this.stateMachineStateRepository.save(this.stateMachineState);

                this.createNotificationSuccess({
                    title: this.$tc('global.default.success'),
                    message: this.$tc('sw-settings-state-machine.state.notification.successMessage'),
                });

                this.$emit('modal-close');
            } catch {
                this.createNotificationError({
                    message: this.$tc('sw-settings-state-machine.state.notification.errorMessage'),
                });
            }
        },
    },
});
