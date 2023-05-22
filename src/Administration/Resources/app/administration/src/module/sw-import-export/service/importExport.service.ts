/**
 * @package system-settings
 */
import ApiService from 'src/core/service/api.service';
import type { AxiosInstance } from 'axios';
import type { LoginService } from '../../../core/service/login.service';

/**
 * @private
 */
export default class ImportExportService extends ApiService {
    onProgressStartedListener: Array<() => unknown> = [];

    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'import-export') {
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
    async export(profileId: string, callback: () => unknown, config: unknown) {
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
    async getDownloadUrl(fileId: string) {
        const accessTokenResponse: { data: { fileId: string, accessToken: string }} = await this.httpClient.post(
            `/_action/import-export/file/prepare-download/${fileId}`,
            {},
            { headers: this.getBasicHeaders() },
        );

        return `${Shopware.Context.api.apiPath || ''}/_action/${this.getApiBasePath()}/` +
            `file/download?fileId=${fileId}&accessToken=${accessTokenResponse.data.accessToken}`;
    }

    /**
     * Get url for profile template download.
     *
     * @param profileId {string}
     * @returns {string}
     */
    async getTemplateFileDownloadUrl(profileId: string) {
        const prepareResponse: { data: { fileId: string, accessToken: string }} = await this.httpClient.post(
            `/_action/import-export/prepare-template-file-download?profileId=${profileId}`,
            {},
            { headers: this.getBasicHeaders() },
        );

        return `${Shopware.Context.api.apiPath || ''}/_action/${this.getApiBasePath()}/` +
            `file/download?fileId=${prepareResponse.data.fileId}&accessToken=${prepareResponse.data.accessToken}`;
    }

    /**
     * Get the mapping from the first line of the CSV file.
     * The mapping contains guessed keys based on the source entity and the given data.
     *
     * @param file {File} The csv file
     * @param sourceEntity {string} the source entity for the mapping
     * @param delimiter {string}
     * @param enclosure {string}
     * @returns {Promise<Object>}
     */
    getMappingFromTemplate(file: File, sourceEntity: string, delimiter = ';', enclosure = '"') {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('sourceEntity', sourceEntity);
        formData.append('delimiter', delimiter);
        formData.append('enclosure', enclosure);

        return this.httpClient.post('/_action/import-export/mapping-from-template', formData, {
            headers: this.getBasicHeaders(),
        }).then((response) => {
            if (!response.data) {
                return Promise.reject(new Error('Empty response data'));
            }

            return Promise.resolve(response.data);
        });
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
    async import(profileId: string, file: File, callback: () => unknown, config = {}, dryRun = false) {
        const expireDate = new Date();
        expireDate.setDate(expireDate.getDate() + 30);

        const formData = new FormData();
        if (file) {
            formData.append('file', file);
        }
        formData.append('profileId', profileId);
        formData.append('expireDate', expireDate.toDateString());
        if (dryRun) {
            formData.append('dryRun', 'true');
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
    async trackProgress(logEntry: { data: { log: { id: string }}}, callback: (log?: { id: string }) => unknown) {
        await this.httpClient.post('/_action/import-export/process', {
            logId: logEntry.data.log.id,
        }, { headers: this.getBasicHeaders() });

        callback.call(this, logEntry.data.log);

        this.onProgressStartedListener.forEach((listenerCallback) => {
            listenerCallback.call(this);
        });

        return logEntry;
    }

    /**
     * @param callback
     * @returns void
     */
    addOnProgressStartedListener(callback: () => unknown) {
        this.onProgressStartedListener.push(callback);
    }

    /**
     * @param logId {String} log id
     * @returns {*} - ApiService.handleResponse(response)
     */
    cancel(logId: string) {
        return this.httpClient.post(`/_action/${this.getApiBasePath()}/cancel`, {
            logId: logId,
        }, { headers: this.getBasicHeaders() }).then(response => ApiService.handleResponse(response));
    }
}
