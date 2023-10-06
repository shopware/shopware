import ApiService from '../api.service';

const { Service } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class TagApiService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, null, 'application/json');
        this.name = 'tagApiService';
    }

    /**
     * @param params: RequestParams
     * @param filters: Object
     * @param additionalHeaders: Object
     * @returns {*} - ApiService.handleResponse(response)
     */
    filterIds(params, filters = {}, additionalHeaders = {}) {
        return this.httpClient.post(
            '_admin/tag-filter-ids',
            { ...params, ...filters },
            {
                headers: this.getBasicHeaders(additionalHeaders),
            },
        ).then(response => ApiService.handleResponse(response));
    }

    /**
     * @param ids: Array
     * @param name: String
     * @param definitionProperties: Object
     * @param bulkMergeProgress: Object
     */
    async merge(ids, name, definitionProperties, bulkMergeProgress) {
        const limit = 200;
        const tagRepository = this.getRepository('tag');

        bulkMergeProgress.isRunning = true;

        const tag = tagRepository.create();
        tag.name = name;

        await tagRepository.save(tag);
        tag._isNew = false;

        // eslint-disable-next-line
        for (const [propertyName, property] of Object.entries(definitionProperties)) {
            if (property.relation !== 'many_to_many') {
                // eslint-disable-next-line
                continue;
            }

            let page = 1;
            bulkMergeProgress.currentAssignment = propertyName;
            bulkMergeProgress.progress = 0;
            bulkMergeProgress.total = 0;

            const repository = this.getRepository(property.entity);

            do {
                const criteria = new Criteria(page, limit);
                criteria.addFilter(Criteria.equalsAny('tags.id', ids));

                // eslint-disable-next-line
                const { data, total } = await repository.searchIds(criteria, Shopware.Context.api);
                tag[propertyName] = data.map((id) => { return { id }; });

                if (total !== 0) {
                    bulkMergeProgress.total = total;
                    // eslint-disable-next-line
                    await tagRepository.save(tag);
                }
                tag[propertyName] = [];

                bulkMergeProgress.progress += data.length;
                page += 1;
            } while (bulkMergeProgress.isRunning && bulkMergeProgress.progress < bulkMergeProgress.total);
        }

        if (!bulkMergeProgress.isRunning) {
            return;
        }

        await tagRepository.syncDeleted(ids, Shopware.Context.api);
    }

    getRepository(entity) {
        return Service('repositoryFactory').create(entity);
    }
}
