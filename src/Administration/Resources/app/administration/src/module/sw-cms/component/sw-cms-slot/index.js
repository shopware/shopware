import template from './sw-cms-slot.html.twig';
import './sw-cms-slot.scss';

const { Component, Context, Data } = Shopware;
const { Criteria } = Data;

Component.register('sw-cms-slot', {
    template,

    inject: [
        'cmsService',
        'repositoryFactory'
    ],

    props: {
        element: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },

        active: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            showElementSettings: false,
            showElementSelection: false,
            showOverrides: false,
            categorySlotConfigOverrides: null
        };
    },

    created() {
        const criteria = new Criteria();
        // TODO use something similar to this to not run into Syntax error or access violation: 3143 Invalid JSON path expression when ids start with a digit
        // criteria.addFilter(Criteria.not('OR', [Criteria.equals(`slotConfig.${this.element.id}`, null)]));
        criteria.addFilter(Criteria.contains('slotConfig', this.element.id));

        this.categoryRepository.search(criteria, Context.api)
            .then(categories => {
                this.categorySlotConfigOverrides = categories;
            });
    },

    computed: {
        elementConfig() {
            return this.cmsService.getCmsElementConfigByName(this.element.type);
        },

        cmsElements() {
            return this.cmsService.getCmsElementRegistry();
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        cmsSlotSettingsClasses() {
            if (this.elementConfig.defaultConfig) {
                return null;
            }
            return 'is--disabled';
        },

        tooltipDisabled() {
            if (this.elementConfig.disabledConfigInfoTextKey) {
                return {
                    message: this.$tc(this.elementConfig.disabledConfigInfoTextKey)
                };
            }
            return {
                message: this.$tc('sw-cms.elements.configTabSettings'),
                disabled: true
            };
        }
    },

    methods: {
        onSettingsButtonClick() {
            if (!this.elementConfig.defaultConfig || this.element.locked) {
                return;
            }
            this.showElementSettings = true;
        },

        onCloseSettingsModal() {
            this.showElementSettings = false;
        },

        onOverrideButtonClick() {
            this.showOverrides = true;
        },

        onCloseOverrideModal() {
            this.showOverrides = false;
        },

        onElementButtonClick() {
            this.showElementSelection = true;
        },

        onCloseElementModal() {
            this.showElementSelection = false;
        },

        onSelectElement(elementType) {
            this.element.data = {};
            this.element.config = {};
            this.element.type = elementType;
            this.showElementSelection = false;
        },

        gotoCategory(categoryId) {
            this.showOverrides = false;
            this.$nextTick(() => {
                this.$router.push({
                    name: 'sw.category.detail',
                    params: {
                        id: categoryId
                    }
                });
            });
        }
    }
});
