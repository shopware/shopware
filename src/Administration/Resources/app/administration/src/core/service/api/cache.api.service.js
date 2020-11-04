class CacheApiService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
        this.name = 'cacheApiService';
    }

    info() {
        const headers = this.getHeaders();
        return this.httpClient.get('/_action/cache_info', { headers });
    }

    index() {
        const headers = this.getHeaders();
        return this.httpClient.post('/_action/index', {}, { headers });
    }

    clear() {
        const headers = this.getHeaders();
        return this.httpClient.delete('/_action/cache', { headers }).then((response) => {
            if (response.status === 204) {
                this.httpClient.delete('/_action/container_cache', { headers });
            }
        });
    }

    cleanupOldCaches() {
        const headers = this.getHeaders();
        return this.httpClient.delete('/_action/cleanup', { headers });
    }

    clearAndWarmup() {
        const headers = this.getHeaders();
        return this.httpClient.delete('/_action/cache_warmup', { headers });
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
