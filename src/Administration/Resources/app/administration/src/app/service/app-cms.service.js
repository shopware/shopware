const { Locale } = Shopware;
const { debug } = Shopware.Utils;

/**
 * Contains a list of allowed block categories
 * @type {string[]}
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const BLOCKS_CATEGORIES = [
    'text', 'image', 'video', 'text-image', 'sidebar', 'commerce', 'form',
];

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class AppCmsService {
    /**
     * Default block configuration
     * @type {{prefix: string}}
     */
    defaultBlockConfig = {
        prefix: 'sw-cms-block-',
        componentSuffix: '-component',
        previewComponentSuffix: '-preview-component',
    };

    /**
     * Contains the custom css styles for the registered cms block elements.
     * @type {string}
     * @private
     */
    blockStyles = '';

    /**
     * @constructor
     * @param {AppCmsBlocksService} appCmsBlocksService
     * @param {VueAdapter} vueAdapter
     * @return Promise<AppCmsService>
     */
    constructor(appCmsBlocksService, vueAdapter) {
        this.appCmsBlocksService = appCmsBlocksService;
        this.vueAdapter = vueAdapter;

        // eslint-disable-next-line no-constructor-return
        return this.requestAppSystemBlocks().then((blocks) => {
            if (!blocks) {
                return this;
            }

            this.iterateCmsBlocks(blocks);
            this.injectStyleTag();

            return this;
        });
    }

    /**
     * Requests registered app system cms blocks from the rest api.
     * @returns {Promise<Object>}
     */
    requestAppSystemBlocks() {
        return this.appCmsBlocksService.fetchAppBlocks();
    }

    /**
     * Iterates over requested cms blocks
     * @param {Array<Object>} blocks
     * @returns {boolean}
     */
    iterateCmsBlocks(blocks) {
        blocks.forEach(this.registerCmsBlock.bind(this));

        return true;
    }

    /**
     * Registers a cms block to the application
     * @param {Object} block
     * @returns {Object}
     */
    registerCmsBlock(block) {
        if (!this.validateBlockCategory(block.category)) {
            debug.warn(
                this.constructor.name,
                `The category "${block.category}" is not a valid category.`,
            );
            return false;
        }

        this.registerBlockSnippets(block.name, block.label);
        this.registerStyles(block);

        const component = this.createBlockComponent(block);
        const previewComponent = this.createBlockPreviewComponent(block);
        const config = this.createBlockConfiguration(block, component, previewComponent);

        Shopware.Service('cmsService').registerCmsBlock(config);

        return config;
    }

    /**
     * Creates the necessary block configuration which is necessary to register the block successfully.
     * @param {Object} block
     * @param {Object} component
     * @param {Object} previewComponent
     * @returns {Object}
     */
    createBlockConfiguration(block, component, previewComponent) {
        return {
            name: `${block.name}${this.defaultBlockConfig.componentSuffix}`,
            label: `sw-app-system-cms.label-${block.name}`,
            category: block.category,
            slots: block.slots,
            defaultConfig: block.defaultConfig,
            component,
            previewComponent,
        };
    }

    /**
     * Returns a new Vue.js component which contains a render function for the component.
     * @param {Object} block
     * @returns {Object}
     */
    createBlockComponent(block) {
        const config = this.defaultBlockConfig;
        const componentName = `${config.prefix}${block.name}${config.componentSuffix}`;

        const component = {
            name: componentName,

            render(h) {
                return h('div', {
                    class: componentName,
                }, [...Object.keys(block.slots).map((slotName) => {
                    return this.$scopedSlots[slotName]();
                })]);
            },
        };

        this.vueAdapter.buildAndCreateComponent(component);
        return component;
    }

    /**
     * Returns a new Vue.js component which contains a render function for the preview component.
     * @param {Object} block
     * @returns {Object}
     */
    createBlockPreviewComponent(block) {
        const config = this.defaultBlockConfig;
        const componentName = `${config.prefix}${block.name}${config.previewComponentSuffix}`;

        const component = {
            name: componentName,
            template: block.template,
        };

        this.vueAdapter.buildAndCreateComponent(component);
        return component;
    }

    /**
     * Registers block label to the global locale factory.
     * @param {string} blockName
     * @param {string} label
     * @returns {boolean}
     */
    registerBlockSnippets(blockName, label) {
        return Object.keys(label).reduce((accumulator, localeKey) => {
            if (!Locale.getByName(localeKey)) {
                debug.warn(
                    this.constructor.name,
                    `The locale "${localeKey}" is not registered in Shopware.Locale.`,
                );

                accumulator = false;
                return accumulator;
            }

            Locale.extend(localeKey, {
                'sw-app-system-cms': {
                    [`label-${blockName}`]: label[localeKey],
                },
            });

            return accumulator;
        }, true);
    }

    /**
     * Validates the category of the block.
     * @param {string} categoryName
     * @returns {boolean}
     */
    validateBlockCategory(categoryName) {
        return BLOCKS_CATEGORIES.includes(categoryName);
    }

    /**
     * Sets the default configuration for blocks
     * @param {Object} config
     * @returns {boolean}
     */
    setDefaultConfig(config) {
        this.defaultBlockConfig = { ...this.defaultBlockConfig, ...config };
        return true;
    }

    /**
     * Registers custom styles for a CMS block.
     * @param {Object} block
     * @returns {boolean}
     */
    registerStyles(block) {
        let customStyles = '';
        if (!block.styles || block.styles.length <= 0) {
            return false;
        }
        customStyles = block.styles;

        this.blockStyles = `${this.blockStyles}${customStyles}`;
        return true;
    }

    /**
     * Injects a style tag inside the document head for the custom style of CMS blocks.
     * @returns {boolean}
     */
    injectStyleTag() {
        if (!this.blockStyles.length) {
            return false;
        }

        const tag = document.createElement('style');
        tag.setAttribute('type', 'text/css');
        tag.appendChild(document.createTextNode(this.blockStyles));
        document.head.appendChild(tag);

        return true;
    }
}
