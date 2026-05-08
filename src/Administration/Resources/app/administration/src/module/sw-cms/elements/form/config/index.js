import template from './sw-cms-el-config-form.html.twig';
import './sw-cms-el-config-form.scss';

const { Mixin } = Shopware;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    inject: ['systemConfigApiService'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        Shopware() {
            return Shopware;
        },

        tabItems() {
            const items = [
                { label: this.$t('sw-cms.elements.general.config.tab.content'), name: 'content' },
            ];

            if (this.requireConfigTab) {
                items.push({ label: this.$t('sw-cms.elements.general.config.tab.settings'), name: 'options' });
            }

            return items;
        },

        tabPositionIdentifier() {
            return 'sw-cms-element-config-form';
        },

        activeTabIsExtensionTab() {
            return this.isRegisteredExtensionTab(this.activeTab);
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

    data() {
        return {
            activeTab: 'content',
        };
    },

    methods: {
        createdComponent() {
            this.initElementConfig('form');
        },

        onNewTabActive(activeItem) {
            const activeTabName = typeof activeItem === 'string' ? activeItem : activeItem?.name;

            if (!activeTabName) {
                return;
            }

            if (!this.isCoreTab(activeTabName) && !this.isRegisteredExtensionTab(activeTabName)) {
                return;
            }

            this.activeTab = activeTabName;
        },

        isCoreTab(tabName) {
            return this.tabItems.some((tab) => tab.name === tabName);
        },

        isRegisteredExtensionTab(tabName) {
            return (Shopware.Store.get('tabs').tabItems[this.tabPositionIdentifier] ?? []).some((tab) => {
                return tab.componentSectionId === tabName;
            });
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
