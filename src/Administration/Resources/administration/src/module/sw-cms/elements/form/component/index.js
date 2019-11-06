import { Component, Mixin } from 'src/core/shopware';
import template from './sw-cms-el-form.html.twig';
import formContact from './sw-cms-el-form-contact.html.twig';
import formNewsletter from './sw-cms-el-form-newsletter.html.twig';

Component.register('sw-cms-el-form', {
    // template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    render() {
        // console.log(this);
        return template; // compile('<div>Hi</div>'); //.render;
    },

    computed: {
        form() {
            if (this.element.config.type.value === 'contact') {
                return formContact;
            } if (this.element.config.type.value === 'newsletter') {
                return formNewsletter;
            }
            return '<div>default</div>';
        }
    },

    created() {
        this.createdComponent();
    },
    updated() {
        console.log(this);
    },

    methods: {
        // you have to call the method initElementConfig from the cms-element mixin.
        // This will take care of dealing with the configComponent and thus providing the configured values.
        createdComponent() {
            this.initElementConfig('form');
        }
    }
});
