import template from './sw-generic-custom-entity-list.html.twig';

const { Component } = Shopware;

Component.register('sw-generic-custom-entity-list', {
    template,

    inject: [
        'customEntityDefinitionService',
    ],

    computed: {
        customEntity() {
            const entityName = this.$route.params.entityName;
            return JSON.stringify(this.customEntityDefinitionService.getConfigByName(entityName));
        },
    },
});
