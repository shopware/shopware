import { Component } from 'src/core/shopware';
import template from './sw-first-run-wizard-modal.html.twig';
import './sw-first-run-wizard-modal.scss';

Component.register('sw-first-run-wizard-modal', {
    template,

    props: {
        title: {
            type: String,
            required: true,
            default: 'unknown title'
        }
    },

    data() {
        return {
            stepIndex: 0,
            stepVariant: 'info',
            stepInitialItemVariants: [
                'disabled',
                'disabled',
                'disabled',
                'disabled'
            ]
        };
    }
});

