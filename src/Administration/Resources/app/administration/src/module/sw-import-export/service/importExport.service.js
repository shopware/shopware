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
     * @param config {Object} Additional config for profile
     * @returns {Promise<void>}
     */
    async export(profileId, callback, config) {
        const expireDate = new Date();
        expireDate.setDate(expireDate.getDate() + 30);

        const createdLog = await this.httpClient.post('/_action/import-export/prepare', {
            profileId: profileId,
            config: config,
            expireDate: expireDate.toDateString(),
        }, { headers: this.getBasicHeaders() });

        return this.trackProgress(createdLog, callback);
    }

    /**
     * Download the export file
     *
     * @param fileId {Entity} File entity
     * @returns {string}
     */
    async getDownloadUrl(fileId) {
        const accessTokenResponse = await this.httpClient.post(
            `/_action/import-export/file/prepare-download/${fileId}`,
            {},
            { headers: this.getBasicHeaders() },
        );

        return `${Shopware.Context.api.apiPath}/_action/${this.getApiBasePath()}/` +
            `file/download?fileId=${fileId}&accessToken=${accessTokenResponse.data.accessToken}`;
    }

    /**
     * Get url for profile template download.
     *
     * @internal (flag:FEATURE_NEXT_15998)
     * @param profileId {string}
     * @returns {string}
     */
    async getTemplateFileDownloadUrl(profileId) {
        const prepareResponse = await this.httpClient.post(
            `/_action/import-export/prepare-template-file-download?profileId=${profileId}`,
            {},
            { headers: this.getBasicHeaders() },
        );

        return `${Shopware.Context.api.apiPath}/_action/${this.getApiBasePath()}/` +
            `file/download?fileId=${prepareResponse.data.fileId}&accessToken=${prepareResponse.data.accessToken}`;
    }

    /**
     * Imports data from the csv file with the given profile. The callback function gets called with progress information
     * and final result data.
     *
     * @param profileId {String} Profile entity
     * @param file {File} The csv file
     * @param callback {Function} Callback for progress
     * @param config {Object} Additional config for profile
     * @param dryRun {Boolean} Set if import is a dry run
     * @returns {Promise<void>}
     */
    async import(profileId, file, callback, config = {}, dryRun = false) {
        const expireDate = new Date();
        expireDate.setDate(expireDate.getDate() + 30);

        const formData = new FormData();
        if (file) {
            formData.append('file', file);
        }
        formData.append('profileId', profileId);
        formData.append('expireDate', expireDate.toDateString());
        if (dryRun) {
            formData.append('dryRun', true);
        }

        Object.entries(config).forEach(([key, value]) => {
            formData.append(`config[${key}]`, JSON.stringify(value));
        });


        const createdLog = await this.httpClient.post('/_action/import-export/prepare', formData, {
            headers: this.getBasicHeaders(),
        });

        return this.trackProgress(createdLog, callback);
    }

    /**
     * @param logEntry {String} log entity
     * @param callback
     * @returns {Promise<void>}
     */
    async trackProgress(logEntry, callback) {
        await this.httpClient.post('/_action/import-export/process', {
            logId: logEntry.data.log.id,
        }, { headers: this.getBasicHeaders() });

        callback.call(this, logEntry.data.log);

        return logEntry;
    }
}
