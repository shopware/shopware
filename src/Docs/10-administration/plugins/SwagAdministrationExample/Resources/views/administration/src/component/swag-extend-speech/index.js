import { Component } from 'src/core/shopware';
import template from './swag-extend-speech.html.twig';


Component.extend('swag-extend-speech', 'swag-speech', {
    template,

    methods: {
        alertElement() {
            console.log('I can be included by using <swag-extend-speech></swag-extend-speech>');
        }
    }
});
