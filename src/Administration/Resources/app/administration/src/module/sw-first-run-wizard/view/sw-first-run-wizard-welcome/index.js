import template from './sw-first-run-wizard-welcome.html.twig';
import './sw-first-run-wizard-welcome.scss';

/**
 * @sw-package fundamentals@after-sales
 *
 * @private
 */
export default {
    template,

    emits: [
        'frw-set-title',
        'buttons-update',
    ],

    mixins: [
        'notification',
    ],

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateButtons();
            this.setTitle();
        },

        setTitle() {
            this.$emit('frw-set-title', this.$t('sw-first-run-wizard.welcome.modalTitle'));
        },

        updateButtons() {
            const disabledExtensionManagement =
                Shopware.Store.get('context').app.config.settings?.disableExtensionManagement;
            const nextRoute = disabledExtensionManagement ? 'defaults' : 'data-import';

            const buttonConfig = [
                {
                    key: 'next',
                    label: this.$t('sw-first-run-wizard.general.buttonNext'),
                    position: 'right',
                    variant: 'primary',
                    action: `sw.first.run.wizard.index.${nextRoute}`,
                    disabled: false,
                },
            ];

            this.$emit('buttons-update', buttonConfig);
        },
    },
};
