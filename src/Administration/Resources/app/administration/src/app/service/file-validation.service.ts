type MimeTypes = {
    [key: string]: string[];
};

type FileValidationService = {
    extensionByType: MimeTypes;
    checkByExtension: (file: File, extensionAccept: string, mimeOverride: MimeTypes) => boolean;
    checkByType: (file: File, mimeAccept: string) => boolean;
};

/**
 * @package services-settings
 * @private
 * @method fileHelperService
 * @returns FileHelperServiceType
 */
export default function fileValidationService(): FileValidationService {
    const extensionByType: MimeTypes = {
        'image/jpeg': [
            'jpg',
            'jpeg',
        ],
        'image/png': ['png'],
        'image/webp': ['webp'],
        'image/avif': ['avif'],
        'image/gif': ['gif'],
        'image/svg+xml': ['svg'],
        'image/bmp': ['bmp'],
        'image/x-ms-bmp': ['bmp'],
        'image/tiff': [
            'tif',
            'tiff',
        ],
        'application/postscript': ['eps'],
        'video/webm': ['webm'],
        'video/x-matroska': ['mkv'],
        'video/x-flv': ['flv'],
        'video/ogg': ['ogv'],
        'audio/ogg': [
            'ogg',
            'ogv',
            'oga',
        ],
        'video/quicktime': ['mov'],
        'video/mp4': ['mp4'],
        'audio/mp4': ['mp4'],
        'video/x-msvideo': ['avi'],
        'video/x-ms-wmv': ['wmv'],
        'video/x-ms-asf': ['wmv'],
        'application/pdf': ['pdf'],
        'audio/aac': ['aac'],
        'audio/vnd.dlna.adts': ['aac'],
        'audio/x-hx-aac-adts': ['aac'],
        'video/mp3': ['mp3'],
        'audio/mp3': ['mp3'],
        'audio/mpeg': ['mp3'],
        'audio/wav': ['wav'],
        'audio/x-wav': ['wav'],
        'audio/x-ms-wma': ['wma'],
        'audio/x-flac': ['flac'],
        'text/plain': ['txt'],
        'application/msword': ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': ['docx'],
        'image/vnd.microsoft.icon': ['ico'],
        'image/x-icon': ['ico'],
        'application/zip': ['zip'],
        'application/vnd.rar': ['rar'],
        'application/json': ['json'],
        'application/xml': ['xml'],
        'application/x-shockwave-flash': ['swf'],
        'application/octet-stream': ['bin'],
        'application/x-rar': ['rar'],
        'application/x-rar-compressed': ['rar'],
        'application/x-tar': ['tar'],
        'application/x-gzip': ['gzip'],
        'application/x-bzip2': ['bz2'],
        'application/x-7z-compressed': ['7z'],
        'application/x-zip': ['zip'],
        'application/x-zip-compressed': ['zip'],
        'application/vnd.android.package-archive': ['apk'],
        'application/vnd.apple.keynote': ['key'],
        'application/vnd.apple.pages': ['pages'],
        'application/vnd.apple.numbers': ['numbers'],
        'application/vnd.ms-excel': ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': [
            'xlsx',
        ],
        'application/vnd.ms-powerpoint': ['ppt'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation': ['pptx'],
        'application/vnd.oasis.opendocument.text': ['odt'],
        'application/vnd.oasis.opendocument.spreadsheet': ['ods'],
        'application/vnd.oasis.opendocument.presentation': ['odp'],
    };

    /**
     * @example
     * checkByExtension(file, 'png, pdf, svg', {...});
     */
    function checkByExtension(file: File, extensionAccept: string, mimeOverride: MimeTypes): boolean {
        if (extensionAccept === '*') {
            return true;
        }

        const fileExtensions: string[] = extensionAccept.replace(/\s/g, '').split(',');

        const types = Object.assign(extensionByType, mimeOverride);

        return fileExtensions.some((extension) => {
            const currentFileExtension = file.name.split('.').at(-1);

            if (!currentFileExtension) {
                return false;
            }

            if (extension !== currentFileExtension) {
                return false;
            }

            if (!types.hasOwnProperty(file.type)) {
                return false;
            }

            return types[file.type].includes(currentFileExtension);
        });
    }

    /**
     * @example
     * checkByExtension(file, 'image/png, application/*', {...});
     */
    function checkByType(file: File, mimeAccept: string): boolean {
        if (mimeAccept === '*/*') {
            return true;
        }

        const fileTypes = mimeAccept.replace(/\s/g, '').split(',');
        const currentFileType = file.type.split('/');

        return fileTypes.some((fileType) => {
            const fileAcceptType = fileType.split('/');

            if (mimeAccept === 'model/gltf-binary' && file.name.split('.').at(-1) === 'glb' && file.type === '') {
                return true;
            }

            if (fileAcceptType[0] !== currentFileType[0] && fileAcceptType[0] !== '*') {
                return false;
            }

            if (fileAcceptType[1] === '*') {
                return true;
            }

            return fileAcceptType[1] === currentFileType[1];
        });
    }

    return {
        extensionByType,
        checkByExtension,
        checkByType,
    };
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { FileValidationService };
