import ApiService from '../api.service';

export default class AppCmsBlocksService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, null, 'application/json');
        this.name = 'appCmsBlocks';
    }

    /**
     * Fetches CMS blocks which are provided by third-party applications.
     * @returns {Promise<Object>}
     */
    fetchAppBlocks() {
        return this.httpClient.get(
            'app-system/cms/blocks',
            {
                headers: this.getBasicHeaders(),
            },
        ).then(({ data }) => {
            return data.blocks;
        });
    }
}
