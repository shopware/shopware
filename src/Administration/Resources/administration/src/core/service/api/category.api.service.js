import ApiService from './api.service';

class CategoryApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'category', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default CategoryApiService;
