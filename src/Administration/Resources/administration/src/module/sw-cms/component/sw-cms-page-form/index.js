import { Component } from 'src/core/shopware';
import template from './sw-cms-page-form.html.twig';
import './sw-cms-page-form.scss';

Component.register('sw-cms-page-form', {
    template,

    inject: ['cmsService'],

    props: {
        page: {
            type: Object,
            required: true
        }
    },

    computed: {
        cmsBlocks() {
            return this.cmsService.getCmsBlockRegistry();
        },

        cmsElements() {
            return this.cmsService.getCmsElementRegistry();
        }
    },

    methods: {
        getBlockTitle(block) {
            if (block.config && block.config.name) {
                return block.config.name;
            }

            if (typeof this.cmsBlocks[block.type] !== 'undefined') {
                return this.cmsBlocks[block.type].label;
            }

            return '';
        }
    }
});
