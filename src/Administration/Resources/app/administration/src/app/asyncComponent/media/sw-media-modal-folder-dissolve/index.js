import template from './sw-media-modal-folder-dissolve.html.twig';

const { Mixin } = Shopware;

/**
 * @status ready
 * @description The <u>sw-media-modal-folder-dissolve</u> component is used to validate the dissolve folder action.
 * @package content
 * @example-type code-only
 * @component-example
 * <sw-media-modal-folder-dissolve :itemsToDissolve="[items]">
 * </sw-media-modal-folder-dissolve>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['mediaFolderService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        itemsToDissolve: {
            required: true,
            type: Array,
            validator(value) {
                return (value.length !== 0);
            },
        },
    },

    methods: {
        closeDissolveModal(originalDomEvent) {
            this.$emit('media-folder-dissolve-modal-close', { originalDomEvent });
        },

        async _dissolveSelection(item) {
            item.isLoading = true;

            try {
                await this.mediaFolderService.dissolveFolder(item.id);

                this.createNotificationSuccess({
                    title: this.$root.$tc('global.default.success'),
                    message: this.$root.$tc(
                        'global.sw-media-modal-folder-dissolve.notification.successSingle.message',
                        1,
                        { folderName: item.name },
                    ),
                });
                return item.id;
            } catch {
                this.createNotificationError({
                    title: this.$root.$tc('global.default.error'),
                    message: this.$root.$tc(
                        'global.sw-media-modal-folder-dissolve.notification.errorSingle.message',
                        1,
                        { folderName: item.name },
                    ),
                });

                return null;
            } finally {
                item.isLoading = false;
            }
        },

        async dissolveSelection() {
            const dissolvedIds = [];

            try {
                await Promise.all(this.itemsToDissolve.map((item) => {
                    dissolvedIds.push(item.id);
                    return this._dissolveSelection(item);
                }));

                if (this.itemsToDissolve.length > 1) {
                    this.createNotificationSuccess({
                        title: this.$root.$tc('global.default.success'),
                        message: this.$root.$tc(
                            'global.sw-media-modal-folder-dissolve.notification.successOverall.message',
                        ),
                    });
                }

                this.$emit(
                    'media-folder-dissolve-modal-dissolve',
                    dissolvedIds,
                );
            } catch {
                if (this.itemsToDissolve.length > 1) {
                    this.createNotificationError({
                        title: this.$root.$tc('global.default.error'),
                        message: this.$root.$tc(
                            'global.sw-media-modal-folder-dissolve.notification.errorOverall.message',
                        ),
                    });
                }
            }
        },
    },
};
