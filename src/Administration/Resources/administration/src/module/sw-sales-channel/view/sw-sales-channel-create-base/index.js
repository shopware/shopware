import { Component } from 'src/core/shopware';

Component.extend('sw-sales-channel-create-base', 'sw-sales-channel-detail-base', {
    created() {
        this.onGenerateKeys();
    }
});
