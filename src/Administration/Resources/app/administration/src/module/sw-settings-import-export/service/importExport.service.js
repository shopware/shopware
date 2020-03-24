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
     * @param cb {Function} Callback for progress
     * @returns {Promise<void>}
     */
    async export(profileId, cb) {
        const headers = this.getBasicHeaders();

        const expireDate = new Date();
        expireDate.setDate(expireDate.getDate() + 30);

        const createdLog = await this.httpClient.post('/_action/import-export/initiate', {
            profileId: profileId,
            expireDate: expireDate.toDateString()
        }, {
            headers
        });

        let data = { data: { offset: 0 } };

        do {
            var oldOffset = data.data.offset;

            data = await this.httpClient.post('/_action/import-export/process', {
                logId: createdLog.data.log.id,
                offset: oldOffset
            }, {
                headers
            });
        } while (oldOffset !== data.data.offset);
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
     * @param cb {Function} Callback for progress
     * @returns {Promise<void>}
     */
    async import(profileId, file, cb) {
        const expireDate = new Date();
        expireDate.setDate(expireDate.getDate() + 30);

        const formData = new FormData();
        if (file) {
            formData.append('file', file);
        }
        formData.append('profileId', profileId);
        formData.append('expireDate', expireDate.toDateString());


        const createdLog = await this.httpClient.post('/_action/import-export/initiate', formData, {
            headers: this.getBasicHeaders()
        });

        let data = { data: { offset: 0 } };

        do {
            var oldOffset = data.data.offset;

            data = await this.httpClient.post('/_action/import-export/process', {
                logId: createdLog.data.log.id,
                offset: oldOffset
            }, {
                headers: this.getBasicHeaders()
            });
        } while (oldOffset !== data.data.offset);


        // this.handleImportProgress(progress, cb);
    }

    /**
     * Recursive function, which requests the new url for fetching progress. The callback get called with every progress
     * information.
     *
     * @param progress {Object}
     * @param cb {Function}
     * @returns {Promise<void>}
     */
    async handleImportProgress(progress, cb) {
        // the callback gets the progress information
        // TODO: an adapter could be needed
        cb(progress);

        if (progress.status === 'finished') {
            // stop recursion when exporting is finished
            return;
        }

        // fetch new progress information from the given url
        // TODO: the implementation could be different when not using the mock
        const newProgress = await this.httpClientMock.post(
            `/_action/import/${progress.progressUrl}`
        );

        // handle the progress
        this.handleImportProgress(newProgress, cb);
    }
}
