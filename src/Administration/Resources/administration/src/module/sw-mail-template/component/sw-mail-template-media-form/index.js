import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-mail-template-media-form.html.twig';
import './sw-mail-template-media-form.scss';

Component.register('sw-mail-template-media-form', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        mailTemplate: {
            type: Object,
            required: true,
            default: {}
        }
    },

    data() {
        return {
            columnCount: 7,
            columnWidth: 90,
            unsavedEntities: []
        };
    },

    computed: {
        mediaItems() {
            const mediaItems = this.mailTemplate.media.slice();
            const placeholderCount = this.getPlaceholderCount(this.columnCount);
            if (placeholderCount === 0) {
                return mediaItems;
            }

            for (let i = 0; i < placeholderCount; i += 1) {
                mediaItems.push(this.createPlaceholderMedia(mediaItems));
            }

            return mediaItems;
        },

        uploadStore() {
            return State.getStore('upload');
        },

        mediaStore() {
            return State.getStore('media');
        },

        mailTemplateMediaStore() {
            return this.mailTemplate.getAssociation('media');
        },

        gridAutoRows() {
            return `grid-auto-rows: ${this.columnWidth}`;
        }
    },

    mounted() {
        this.mountedComponent();
    },

    beforeDestroy() {
        this.onBeforeDestroy();
    },

    methods: {
        mountedComponent() {
            const that = this;
            this.$device.onResize({
                listener() {
                    that.updateColumnCount();
                },
                component: this
            });
            this.updateColumnCount();
        },

        updateColumnCount() {
            this.$nextTick(() => {
                const cssColumns = window.getComputedStyle(this.$refs.grid, null)
                    .getPropertyValue('grid-template-columns')
                    .split(' ');

                this.columnCount = cssColumns.length;
                this.columnWidth = cssColumns[0];
            });
        },

        onBeforeDestroy() {
            this.unsavedEntities.forEach((entity) => {
                this.uploadStore.removeUpload(entity.taskId);
            });
        },

        getPlaceholderCount(columnCount) {
            if (this.mailTemplate.media.length < columnCount * 2) {
                columnCount *= 2;
            }
            const placeholderCount = columnCount - this.mailTemplate.media.length;

            if (placeholderCount === columnCount) {
                return 0;
            }

            return placeholderCount;
        },

        createPlaceholderMedia() {
            return {
                isPlaceholder: true,
                media: {
                    isPlaceholder: true,
                    name: ''
                }
            };
        },

        onUploadsAdded({ data }) {
            if (data.length === 0) {
                return;
            }

            this.mailTemplate.isLoading = true;
            this.mediaStore.sync().then(() => {
                data.forEach((upload) => {
                    if (this.mailTemplate.media.some((pMedia) => {
                        return pMedia.mediaId === upload.targetId;
                    })) {
                        return;
                    }

                    this.mailTemplate.media.push(this.buildMailTemplateMedia(upload.targetId));
                });
                this.mailTemplate.isLoading = false;

                this.uploadStore.runUploads(this.mailTemplate.id);
            });
        },

        onMediaUploadButtonOpenSidebar() {
            this.$root.$emit('sw-mail-template-media-form-open-sidebar');
        },

        buildMailTemplateMedia(mediaId) {
            const mailTemplateMedia = this.mailTemplateMediaStore.create();
            mailTemplateMedia.mediaId = mediaId;

            if (this.mailTemplate.media.length === 0) {
                mailTemplateMedia.position = 0;
            } else {
                mailTemplateMedia.position = this.mailTemplate.media.length + 1;
            }

            return mailTemplateMedia;
        },

        addImageToPreview(sourceURL, mailTemplateMedia) {
            const canvas = document.createElement('canvas');
            const columnWidth = this.columnWidth.split('px')[0];
            const img = new Image();
            img.onload = () => {
                // resize image with aspect ratio
                const dimensions = this.getImageDimensions(img, columnWidth);
                canvas.setAttribute('width', dimensions.width);
                canvas.setAttribute('height', dimensions.height);
                const ctx = canvas.getContext('2d');
                ctx.drawImage(
                    img, 0, 0, canvas.width, canvas.height
                );

                mailTemplateMedia.media.url = canvas.toDataURL();
                mailTemplateMedia.isLoading = false;

                this.$forceUpdate();
            };
            img.src = sourceURL;
        },

        successfulUpload({ targetId }) {
            this.mediaStore.getByIdAsync(targetId).then(() => {
                this.$forceUpdate();
            });
        },

        onUploadFailed(uploadTask) {
            const toRemove = this.mailTemplate.media.find((mailTemplateMedia) => {
                return mailTemplateMedia.mediaId === uploadTask.targetId;
            });
            if (toRemove) {
                this.removeFile(toRemove);
            }
            this.mailTemplate.isLoading = false;
        },

        getImageDimensions(img, size) {
            if (img.width > img.height) {
                return {
                    height: size,
                    width: size * (img.width / img.height)
                };
            }

            return {
                width: size,
                height: size * (img.height / img.width)
            };
        },

        removeFile(mediaItem) {
            const mailTemplateMediaId = mediaItem.id;
            const item = this.mailTemplate.media.find((mailTemplateMedia) => {
                return mailTemplateMedia.id === mailTemplateMediaId;
            });

            this.mailTemplate.media = this.mailTemplate.media.filter(
                (mailTemplateMedia) => mailTemplateMedia.id !== mailTemplateMediaId
                    && mailTemplateMedia !== mailTemplateMediaId
            );

            item.delete();
        }
    }
});
