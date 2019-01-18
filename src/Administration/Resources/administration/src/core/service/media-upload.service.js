/**
 * @module core/service/media-upload
 */
import { fileReader } from 'src/core/service/util.service';

export default function createMediaUploadService(mediaService) {
    return {
        uploadFileToMedia,
        uploadUrlToMedia,
        splitFileNameAndExtension
    };

    function uploadFileToMedia(file, mediaEntity, fileName = '') {
        const pathinfo = splitFileNameAndExtension(file.name);

        if (fileName) {
            pathinfo.fileName = fileName;
        }

        fileReader.readAsDataURL(file).then((dataUrl) => {
            mediaEntity.fileName = pathinfo.fileName;
            mediaEntity.fileExtension = pathinfo.extension;
            mediaEntity.hasFile = true;
            mediaEntity.mimeType = file.type;
            mediaEntity.url = dataUrl;
            mediaEntity.uploadedAt = new Date();
        });

        return fileReader.readAsArrayBuffer(file).then((buffer) => {
            return mediaService.uploadMediaById(
                mediaEntity.id,
                file.type,
                buffer,
                pathinfo.extension,
                pathinfo.fileName
            );
        });
    }

    function uploadUrlToMedia(url, mediaEntity, fileExtension = '', fileName = '') {
        const pathinfo = splitFileNameAndExtension(url.href.split('/').pop());
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

        mediaEntity.fileName = pathinfo.fileName;
        mediaEntity.fileExtension = pathinfo.extension;
        mediaEntity.hasFile = true;
        mediaEntity.url = url.href;
        mediaEntity.uploadedAt = new Date();

        return mediaService.uploadMediaFromUrl(
            mediaEntity.id,
            url.href,
            pathinfo.extension,
            pathinfo.fileName
        );
    }

    function splitFileNameAndExtension(completeFileName) {
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
