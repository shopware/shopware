import type { buttonProps } from '@shopware-ag/admin-extension-sdk/es/ui/modal';
import type { ModalItemEntry } from 'src/app/state/modals.store';
import template from './sw-modals-renderer.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-modals-renderer', {
    template,

    computed: {
        modals(): ModalItemEntry[] {
            return Shopware.State.get('modals').modals;
        },
    },

    methods: {
        closeModal(locationId: string) {
            Shopware.State.commit('modals/closeModal', locationId);
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
