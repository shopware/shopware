import ApiService from 'src/core/service/api.service';

/**
 * @private
 */
export default class ImportExportService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'import-export') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'importExportService';
        this.httpClient = httpClient;
    }

    /**
     * Export data from the Shop with the given profile. The callback function gets called with progress information
     * and final result data.
     *
     * @param profileId {Entity} Profile entity
     * @param callback {Function} Callback for progress
     * @returns {Promise<void>}
     */
    async export(profileId, callback) {
        const expireDate = new Date();
        expireDate.setDate(expireDate.getDate() + 30);

        const createdLog = await this.httpClient.post('/_action/import-export/prepare', {
            profileId: profileId,
            expireDate: expireDate.toDateString()
        }, { headers: this.getBasicHeaders() });

        return this.trackProgress(createdLog, callback);
    }

    /**
     * Download the export file
     *
     * @param fileId {Entity} File entity
     * @param accessToken
     * @returns {Promise<void>}
     */
    getDownloadUrl(fileId, accessToken) {
        return `/api/v1/_action/${this.getApiBasePath()}/file/download?fileId=${fileId}&accessToken=${accessToken}`;
    }

    /**
     * Imports data from the csv file with the given profile. The callback function gets called with progress information
     * and final result data.
     *
     * @param profileId {String} Profile entity
     * @param file {File} The csv file
     * @param callback
     * @returns {Promise<void>}
     */
    async import(profileId, file, callback) {
        const expireDate = new Date();
        expireDate.setDate(expireDate.getDate() + 30);

        const formData = new FormData();
        if (file) {
            formData.append('file', file);
        }
        formData.append('profileId', profileId);
        formData.append('expireDate', expireDate.toDateString());


        const createdLog = await this.httpClient.post('/_action/import-export/prepare', formData, {
            headers: this.getBasicHeaders()
        });

        return this.trackProgress(createdLog, callback);
    }

    /**
     * @param logEntry {String} log entity
     * @param callback
     * @param progress
     * @returns {Promise<void>}
     */
    async trackProgress(logEntry, callback, progress) {
        const { data: { progress: newProgress } } = await this.httpClient.post('/_action/import-export/process', {
            logId: logEntry.data.log.id,
            offset: (progress && progress.offset) ? progress.offset : 0
        }, { headers: this.getBasicHeaders() });

        callback.call(this, newProgress);

        if (newProgress.offset >= newProgress.total) {
            return logEntry;
        }

        return this.trackProgress(logEntry, callback, newProgress);
    }
}
