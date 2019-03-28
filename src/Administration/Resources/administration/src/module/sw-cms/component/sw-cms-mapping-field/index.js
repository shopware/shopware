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
            currentDemoValue: null,
            mappingTypes: {},
            allowedMappingTypes: []
        };
    },

    computed: {
        cmsState() {
            return cmsState;
        },

        isMapped() {
            return this.config.source === 'entity';
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

            this.mappingTypes = this.cmsState.currentMappingTypes;
            this.getAllowedMappingTypes();
        },

        onMappingSelect(property) {
            this.config.source = 'entity';
            this.config.value = property;
            this.currentDemoValue = this.getDemoValue(property);
        },

        onMappingRemove() {
            this.config.source = 'static';
            this.config.value = this.initialStaticValue;
            this.currentDemoValue = null;
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
