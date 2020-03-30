import ApiService from 'src/core/service/api.service';

// TODO: Bitte die ganze Klasse Unit testen!
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

        await this.trackProgress(createdLog, callback);

        return createdLog;
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

        await this.trackProgress(createdLog, callback);

        return createdLog;
    }

    async trackProgress(logEntry, callback) {
        let data = { progress: { offset: 0, total: 1 } };
        let noProgressCounter = 0;

        while (data.progress.offset < data.progress.total) {
            const oldOffset = data.progress.offset;

            // eslint-disable-next-line no-await-in-loop
            const result = await this.httpClient.post('/_action/import-export/process', {
                logId: logEntry.data.log.id,
                offset: oldOffset
            }, { headers: this.getBasicHeaders() });
            data = result.data;

            // Track if no progress was made
            if (oldOffset === data.progress.offset) {
                noProgressCounter += 1;
            } else {
                noProgressCounter = 0;
            }

            if (noProgressCounter >= 3) {
                throw new Error('ImportExportService - Process failed. ' +
                    'The offset did not change within three requests');
            }
            callback.call(this, data.progress);
        }
    }
}
