/**
 * @module core/service/media-upload
 */
import { fileReader } from 'src/core/service/util.service';

export default function createMediaUploadService(mediaService) {
    return {
        uploadFileToMedia,
        uploadUrlToMedia
    };

    function uploadFileToMedia(file, mediaEntity, fileExtension = '') {
        fileExtension = fileExtension || file.type;

        return fileReader.readAsArrayBuffer(file).then((buffer) => {
            return mediaService.uploadMediaById(
                mediaEntity.id,
                fileExtension,
                buffer,
                file.name.split('.').pop()
            );
        });
    }

    function uploadUrlToMedia(url, mediaEntity, fileExtension = '') {
        fileExtension = fileExtension || url.pathname.split('.').pop();

        return mediaService.uploadMediaFromUrl(mediaEntity.id, url.href, fileExtension);
    }
}
