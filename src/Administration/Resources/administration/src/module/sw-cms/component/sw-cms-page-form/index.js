import { Component } from 'src/core/shopware';
import cmsService from 'src/module/sw-cms/service/cms.service';
import template from './sw-cms-page-form.html.twig';
import './sw-cms-page-form.scss';

Component.register('sw-cms-page-form', {
    template,

    props: {
        page: {
            type: Object,
            required: true
        }
    },

    computed: {
        cmsBlocks() {
            return cmsService.getCmsBlockRegistry();
        },

        cmsElements() {
            return cmsService.getCmsElementRegistry();
        }
    }
});
