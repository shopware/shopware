import { Component } from 'src/core/shopware';
import cmsService from 'src/module/sw-cms/service/cms.service';
import cmsState from 'src/module/sw-cms/state/cms-page.state';
import template from './sw-cms-mapping-field.html.twig';
import './sw-cms-mapping-field.scss';

Component.register('sw-cms-mapping-field', {
    template,

    model: {
        prop: 'config',
        event: 'config-update'
    },

    props: {
        config: {
            type: Object,
            required: true,
            default() {
                return {
                    source: 'static',
                    value: null
                };
            }
        },

        valueTypes: {
            type: [String, Array],
            required: false,
            default: 'string'
        },

        label: {
            type: String,
            required: false,
            default: ''
        }
    },

    data() {
        return {
            initialStaticValue: null,
            currentMapping: null,
            mappingTypes: {},
            allowedMappingTypes: [],
            demoValue: null
        };
    },

    computed: {
        cmsState() {
            return cmsState;
        },

        isMapped() {
            return this.config.source === 'mapped';
        },

        hasPreview() {
            return typeof this.$scopedSlots.preview !== 'undefined';
        }
    },

    watch: {
        'cmsState.currentPage.type': {
            handler() {
                this.mappingTypes = this.cmsState.currentMappingTypes;
                this.getAllowedMappingTypes();
            }
        },

        'cmsState.currentDemoEntity': {
            handler() {
                if (this.config.source === 'mapped') {
                    this.demoValue = this.getDemoValue(this.config.value);
                }
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.config.source === 'static' && this.config.value !== null) {
                this.initialStaticValue = this.config.value;
            }

            if (this.config.source === 'mapped') {
                this.demoValue = this.getDemoValue(this.config.value);
            }

            this.mappingTypes = this.cmsState.currentMappingTypes;
            this.getAllowedMappingTypes();
        },

        onMappingSelect(property) {
            this.config.source = 'mapped';
            this.config.value = property;
            this.demoValue = this.getDemoValue(property);
        },

        onMappingRemove() {
            this.config.source = 'static';
            this.config.value = this.initialStaticValue;
            this.demoValue = null;
        },

        getAllowedMappingTypes() {
            let types = [];

            Object.keys(this.mappingTypes).forEach((type) => {
                if (type === this.valueTypes || this.valueTypes.includes(type)) {
                    types = [...types, ...this.mappingTypes[type]];
                }
            });

            this.allowedMappingTypes = types;
        },

        getDemoValue(mappingPath) {
            return cmsService.getPropertyByMappingPath(cmsState.currentDemoEntity, mappingPath);
        }
    }
});
