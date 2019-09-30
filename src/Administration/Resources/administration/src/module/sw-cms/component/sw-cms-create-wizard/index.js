import template from './sw-cms-create-wizard.html.twig';
import './sw-cms-create-wizard.scss';

const { Component } = Shopware;

// ToDo:
Component.register('sw-cms-create-wizard', {
    template,

    inject: ['cmsService'],

    props: {

    },

    data() {
        return {
        };
    },

    computed: {

    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {

        }
    }
});
