import template from './sw-cms-el-config-form.html.twig';
import './sw-cms-el-config-form.scss';

const { Mixin } = Shopware;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    inject: [
        'feature',
        'systemConfigApiService',
    ],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            activeTab: 'content',
        };
    },

    computed: {
        tabs() {
            const tabs = [
                {
                    label: this.$t('sw-cms.elements.general.config.tab.content'),
                    name: 'content',
                },
            ];

            if (this.requireConfigTab) {
                tabs.push({
                    label: this.$t('sw-cms.elements.general.config.tab.settings'),
                    name: 'options',
                });
            }

            return tabs;
        },

        getLastMailClass() {
            if (this.element.config.mailReceiver.value.length === 1) {
                return 'is--last';
            }
            return '';
        },

        formTypeOptions() {
            return [
                {
                    id: 1,
                    value: '',
                    label: this.$t('sw-cms.elements.form.config.label.type'),
                    disabled: true,
                },
                {
                    id: 2,
                    value: 'contact',
                    label: this.$t('sw-cms.elements.form.config.label.typeContact'),
                },
                {
                    id: 3,
                    value: 'newsletter',
                    label: this.$t('sw-cms.elements.form.config.label.typeNewsletter'),
                },
                {
                    id: 4,
                    value: 'revocationRequest',
                    label: this.$t('sw-cms.elements.form.config.label.typeRevocationRequest'),
                },
            ];
        },

        requireConfigTab() {
            return [
                'contact',
                'revocationRequest',
            ].includes(this.element.config.type.value);
        },
    },

    created() {
        this.createdComponent();
        this.setShopMail();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('form');
        },

        async getShopMail() {
            const response = await this.systemConfigApiService.getValues('core.basicInformation');
            return response['core.basicInformation.email'];
        },

        async setShopMail() {
            const shopMail = await this.getShopMail();

            if (
                this.element.config.defaultMailReceiver.value &&
                !this.element.config.mailReceiver.value.includes(shopMail)
            ) {
                this.element.config.mailReceiver.value.push(shopMail);
            }
        },

        async updateMailReceiver(value) {
            this.element.config.mailReceiver.value = value;

            if (!this.validateMail()) {
                return;
            }

            const shopMail = await this.getShopMail();
            this.element.config.defaultMailReceiver.value = this.element.config.mailReceiver.value.includes(shopMail);
        },

        validateMail() {
            const lastMail = this.element.config.mailReceiver.value.at(-1);

            if (lastMail) {
                if (!lastMail.includes('@')) {
                    this.element.config.mailReceiver.value.pop();
                    return false;
                }
            }
            return true;
        },
    },
};
