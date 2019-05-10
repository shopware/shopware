class CacheApiService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
        this.name = 'cacheApiService';
    }

    clear() {
        const headers = this.getHeaders();
        return this.httpClient.delete('/_action/cache', { headers });
    }

    getHeaders() {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json'
        };
    }
}

export default CacheApiService;
