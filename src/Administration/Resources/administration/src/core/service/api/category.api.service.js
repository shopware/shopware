import ApiService from './api.service';

class CategoryApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'category', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default CategoryApiService;
