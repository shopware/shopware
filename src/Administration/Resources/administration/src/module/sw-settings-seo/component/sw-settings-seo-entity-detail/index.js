import { Component } from 'src/core/shopware';
import template from './sw-settings-seo-entity.detail.html.twig';

Component.register('sw-settings-seo-entity-detail', {
    template,

    props: {
        entity: {
            type: Object,
            required: true
        }
    }
});
