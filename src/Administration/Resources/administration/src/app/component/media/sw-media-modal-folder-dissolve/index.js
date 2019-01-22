import { Component, Mixin } from 'src/core/shopware';
import template from './sw-media-modal-folder-dissolve.html.twig';
import './sw-media-modal-folder-dissolve.less';

/**
 * @status ready
 * @description The <u>sw-media-modal-folder-dissolve</u> component is used to validate the dissolve folder action.
 * @example-type code-only
 * @component-example
 * <sw-media-modal-folder-dissolve :itemsToDissolve="[items]">
 * </sw-media-modal-folder-dissolve>
 */
Component.register('sw-media-modal-folder-dissolve', {
    template,

    inject: ['mediaFolderService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        itemsToDissolve: {
            required: true,
            type: Array,
            validator(value) {
                return (value.length !== 0);
            }
        }
    },

    methods: {
        closeDissolveModal(originalDomEvent) {
            this.$emit('sw-media-modal-folder-dissolve-close', { originalDomEvent });
        },

        dissolveSelection() {
            const dissolvePromises = [];
            const notificationMessageSuccess = this.$tc('global.sw-media-modal-folder-dissolve.notificationSuccessOverall');
            const notificationMessageError = this.$tc('global.sw-media-modal-folder-dissolve.notificationErrorOverall');

            this.itemsToDissolve.forEach((item) => {
                const messages = this._getNotificationMessages(item);
                item.isLoading = true;

                dissolvePromises.push(
                    this.mediaFolderService.dissolveFolder(item.id).then(() => {
                        item.isLoading = false;
                        item.remove();
                        this.createNotificationSuccess({
                            message: messages.successMessage
                        });
                        return item.id;
                    }).catch(() => {
                        item.isLoading = false;
                        this.createNotificationError({
                            message: messages.errorMessage
                        });
                    })
                );
            });

            this.$emit(
                'sw-media-modal-folder-dissolve-items-dissolved',
                Promise.all(dissolvePromises).then((ids) => {
                    this.createNotificationSuccess({
                        message: notificationMessageSuccess
                    });
                    return ids;
                }).catch(() => {
                    this.createNotificationError({
                        message: notificationMessageError
                    });
                })
            );
        },

        _getNotificationMessages(item) {
            return {
                successMessage: this.$tc(
                    'global.sw-media-modal-folder-dissolve.notificationSuccessSingle',
                    1,
                    { folderName: item.name }
                ),
                errorMessage: this.$tc(
                    'global.sw-media-modal-folder-dissolve.notificationErrorSingle',
                    1,
                    { folderName: item.name }
                )
            };
        }
    }
});
