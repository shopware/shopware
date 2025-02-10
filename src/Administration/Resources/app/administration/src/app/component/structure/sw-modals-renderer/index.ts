import type { buttonProps } from '@shopware-ag/meteor-admin-sdk/es/ui/modal';
import type { ModalItemEntry } from 'src/app/store/modals.store';
import template from './sw-modals-renderer.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
 *
 * @private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-modals-renderer', {
    template,

    computed: {
        modals(): ModalItemEntry[] {
            return Shopware.Store.get('modals').modals;
        },
    },

    methods: {
        closeModal(locationId: string) {
            Shopware.Store.get('modals').closeModal(locationId);
        },

        buttonProps(button: buttonProps) {
            return {
                method: button.method ?? ((): undefined => undefined),
                label: button.label ?? '',
                size: button.size ?? '',
                variant: button.variant ?? '',
                square: button.square ?? false,
            };
        },
    },
});
