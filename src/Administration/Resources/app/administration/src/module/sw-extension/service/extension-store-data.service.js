import ApiService from 'src/core/service/api.service';

const { Criteria } = Shopware.Data;

export default class ExtensionStoreDataService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'plugin') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'extensionStoreDataService';
    }

    async refreshExtensions(context) {
        await this.httpClient.post('_action/extension/refresh', {}, {
            headers: this.basicHeaders(context),
            version: 3
        });
    }

    async listingFilters(context) {
        const res = await this.httpClient.get('_action/extension/store-filters', {
            headers: this.basicHeaders(context),
            version: 3
        });

        return res.data;
    }

    async getExtensionList(search, context) {
        const criteria = this._getCriteriaFromSearch(search);

        const { data } = await this.httpClient.post('_action/extension/list', criteria.parse(), {
            headers: this.basicHeaders(context),
            version: 3
        });

        const extensions = [];
        extensions.total = data.meta.total;
        extensions.push(...data.data);

        return extensions;
    }

    async getDetail(id, context) {
        const { data } = await this.httpClient.get(`_action/extension/detail/${id}`, {
            headers: this.basicHeaders(context),
            version: 3
        });

        return data;
    }

    async getInstalledExtensions(context) {
        const { data } = await this.httpClient.get('_action/extension/installed', {
            headers: this.basicHeaders(context),
            version: 3
        });

        return data;
    }

    async getReviews(page, limit, id) {
        const criteria = new Criteria(
            page,
            limit
        );

        const { data } = await this.httpClient.get(`_action/extension/${id}/reviews`, {
            headers: this.basicHeaders(),
            params: criteria.parse(),
            version: 3
        });

        return data;
    }

    basicHeaders(context = null) {
        const headers = {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`
        };

        if (context && context.languageId) {
            headers['sw-language-id'] = context.languageId;
        }

        return headers;
    }

    _getCriteriaFromSearch({
        page = 1,
        limit = 25,
        rating = null,
        category = null,
        term = null,
        sorting = null
    } = {}) {
        const criteria = new Criteria(
            page,
            limit
        );

        if (term) {
            criteria.setTerm(term);
        }

        const filters = [];

        if (rating !== null) {
            filters.push(Criteria.equals('rating', rating));
        }

        if (category !== null) {
            filters.push(Criteria.equals('category', category));
        }

        if (filters.length > 0) {
            criteria.addFilter(Criteria.multi('AND', filters));
        }

        if (sorting) {
            criteria.resetSorting();
            criteria.addSorting(sorting);
        }

        return criteria;
    }
}
