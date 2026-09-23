/**
 * @sw-package discovery
 */
import { isRepresentativeMediaType } from 'src/core/service/utils/media-type.utils';

Shopware.Filter.register(
    'mediaName',
    (
        value: {
            entity?: {
                fileName?: string;
                fileExtension?: string;
                mediaType?: { name?: string } | null;
            };
            fileName?: string;
            fileExtension?: string;
            mediaType?: { name?: string } | null;
        },
        fallback: string = '',
    ): string => {
        if (!value) {
            return fallback;
        }

        if (value.entity) {
            value = value.entity;
        }

        if (!value.fileName) {
            return fallback;
        }

        // The file of such a media is a rendering of it, so the extension would describe the
        // stand-in rather than the thing the media represents.
        if (isRepresentativeMediaType(value)) {
            return value.fileName;
        }

        if (!value.fileExtension) {
            return fallback;
        }

        return `${value.fileName}.${value.fileExtension}`;
    },
);

/* @private */
export {};
