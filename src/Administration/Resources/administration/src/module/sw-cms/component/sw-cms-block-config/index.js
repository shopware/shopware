import { Component, Entity } from 'src/core/shopware';
import cmsService from 'src/module/sw-cms/service/cms.service';
import template from './sw-cms-block-config.html.twig';
import './sw-cms-block-config.scss';

Component.register('sw-cms-block-config', {
    template,

    model: {
        prop: 'block',
        event: 'block-update'
    },

    props: {
        block: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        }
    },

    data() {
        return {};
    },

    computed: {
        cmsBlocks() {
            return cmsService.getCmsBlockRegistry();
        }
    },

    watch: {
        block: {
            deep: true,
            handler() {
                this.$emit('block-update', this.block);
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.block.config || this.block.config === null) {
                const blockSchema = Entity.getDefinition('cms_block');
                this.block.config = Entity.getRawEntityObject(blockSchema.properties.config);

                this.block.config.sizingMode = 'boxed';

                if (!this.block.config.name) {
                    this.block.config.name = null;
                }
            }
        }
    }
});
