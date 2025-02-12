import template from './sw-settings-state-machine-detail.html.twig';

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
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('stateMachine'),
    ],

    props: {
        stateMachineId: {
            type: String,
            required: true,
        },
    },

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSave',
        },

        ESCAPE: 'onCancel',
    },

    data() {
        return {
            stateMachine: null,
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.stateMachine?.name ?? '';
        },

        stateMachineRepository() {
            return this.repositoryFactory.create('state_machine');
        },

        allowSave() {
            return this.acl.can('state_machine.editor');
        },

        tooltipSave() {
            if (!this.allowSave) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        ...mapPropertyErrors('stateMachine', [
            'name',
        ]),
    },

    watch: {
        stateMachineId() {
            this.loadStateMachine();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadStateMachine();
        },

        async loadStateMachine() {
            this.isLoading = true;

            try {
                this.stateMachine = await this.stateMachineRepository.get(this.stateMachineId);
            } catch (error) {
                this.createNotificationError({
                    message: this.$tc(error.message),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onChangeLanguage() {
            this.loadStateMachine();

            this.$refs.stateMachineStateList.loadStateMachineStates();
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.state.machine.index' });
        },

        async onSave() {
            if (this.stateMachine === null) {
                return;
            }

            this.isSaveSuccessful = false;
            this.isLoading = true;

            try {
                await this.stateMachineRepository.save(this.stateMachine);
                await this.loadStateMachine();

                this.isSaveSuccessful = true;
            } catch {
                this.createNotificationError({
                    message: this.$tc('sw-settings-state-machine.notification.errorMessage'),
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
