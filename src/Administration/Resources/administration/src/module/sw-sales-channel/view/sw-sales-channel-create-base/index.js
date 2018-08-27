import { Component } from 'src/core/shopware';
import template from './sw-sales-channel-create-base.html.twig';

Component.extend('sw-sales-channel-create-base', 'sw-sales-channel-detail-base', {
    template,

    created() {
        this.onGenerateKeys();
    }
});
