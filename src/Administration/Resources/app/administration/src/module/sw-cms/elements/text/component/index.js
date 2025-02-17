import template from './sw-cms-el-text.html.twig';
import './sw-cms-el-text.scss';
// eslint-disable-next-line max-len
import SwTextEditorToolbarButtonCmsDataMappingButton from '../../../../../app/component/meteor-wrapper/mt-text-editor/sw-text-editor-toolbar-button-cms-data-mapping';

const { Mixin } = Shopware;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            editable: true,
            demoValue: '',
        };
    },

    watch: {
        'cmsPageState.currentDemoEntity': {
            handler() {
                this.updateDemoValue();
            },
        },
        'element.config.content.source': {
            handler() {
                this.updateDemoValue();
            },
        },
    },

    computed: {
        availableDataMappings() {
            let mappings = [];

            Object.entries(Shopware.Store.get('cmsPage').currentMappingTypes).forEach((entry) => {
                const [
                    type,
                    value,
                ] = entry;

                if (type === 'string') {
                    mappings = [
                        ...mappings,
                        ...value,
                    ];
                }
            });

            return mappings;
        },

        customTextEditorButtons() {
            return [
                SwTextEditorToolbarButtonCmsDataMappingButton(() => this.availableDataMappings),
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('text');
        },

        updateDemoValue() {
            if (this.element.config.content.source === 'mapped') {
                this.demoValue = this.getDemoValue(this.element.config.content.value);
            }
        },

        onBlur(content) {
            this.emitChanges(content);
        },

        onInput(content) {
            this.emitChanges(content);
        },

        emitChanges(content) {
            if (content === this.element.config.content.value) {
                return;
            }

            this.element.config.content.value = content;
            this.$emit('element-update', this.element);
        },
    },
};
