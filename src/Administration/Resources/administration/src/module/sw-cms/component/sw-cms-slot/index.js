import { Component } from 'src/core/shopware';
import cmsService from 'src/module/sw-cms/service/cms.service';
import template from './sw-cms-slot.html.twig';
import './sw-cms-slot.scss';

Component.register('sw-cms-slot', {
    template,

    props: {
        element: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },

        active: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            showElementSettings: false,
            showElementSelection: false
        };
    },

    computed: {
        elementConfig() {
            return cmsService.getCmsElementConfigByName(this.element.type);
        },

        cmsElements() {
            return cmsService.getCmsElementRegistry();
        }
    },

    methods: {
        onSettingsButtonClick() {
            this.showElementSettings = true;
        },

        onCloseSettingsModal() {
            this.showElementSettings = false;
        },

        onElementButtonClick() {
            this.showElementSelection = true;
        },

        onCloseElementModal() {
            this.showElementSelection = false;
        },

        onSelectElement(elementType) {
            this.element.data = {};
            this.element.config = {};
            this.element.type = elementType;
            this.showElementSelection = false;
        }
    }
});
