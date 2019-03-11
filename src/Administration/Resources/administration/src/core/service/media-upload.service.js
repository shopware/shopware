/**
 * @module core/service/media-upload
 */
import { fileReader } from 'src/core/service/util.service';

class MediaUploadService {
    constructor(mediaService) {
        this.mediaService = mediaService;
    }

    uploadFileToMedia(file, mediaEntity, fileName = '') {
        const pathinfo = this.splitFileNameAndExtension(file.name);

        if (fileName) {
            pathinfo.fileName = fileName;
        }

        return fileReader.readAsArrayBuffer(file).then((buffer) => {
            return this.mediaService.uploadMediaById(
                mediaEntity.id,
                file.type,
                buffer,
                pathinfo.extension,
                pathinfo.fileName
            );
        });
    }

    uploadUrlToMedia(url, mediaEntity, fileExtension = '', fileName = '') {
        const pathinfo = this.splitFileNameAndExtension(url.href.split('/').pop());
        const indexOfQueryIndicator = pathinfo.fileName.indexOf('?');

        if (indexOfQueryIndicator > 0) {
            pathinfo.fileName = pathinfo.fileName.substring(0, indexOfQueryIndicator);
        }

        if (fileExtension) {
            pathinfo.extension = fileExtension;
        }
        if (fileName) {
            pathinfo.fileName = fileName;
        }

        return this.mediaService.uploadMediaFromUrl(
            mediaEntity.id,
            url.href,
            pathinfo.extension,
            pathinfo.fileName
        );
    }

    splitFileNameAndExtension(completeFileName) {
        const fileParts = completeFileName.split('.');

        // no dot in filename
        if (fileParts.length === 1) {
            return {
                extension: '',
                fileName: completeFileName
            };
        }

        // hidden file without extension
        if (fileParts.length === 2 && !fileParts[0]) {
            return {
                extension: '',
                fileName: completeFileName
            };
        }

        return {
            extension: fileParts.pop(),
            fileName: fileParts.join('.')
        };
    }
}

export default (mediaService) => {
    return new MediaUploadService(mediaService);
};
