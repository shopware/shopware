import template from './sw-settings-seo-entity.detail.html.twig';

const { Component } = Shopware;

Component.register('sw-settings-seo-entity-detail', {
    template,

    props: {
        entity: {
            type: Object,
            required: true
        }
    }
});
