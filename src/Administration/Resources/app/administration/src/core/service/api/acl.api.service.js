class AclApiService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
        this.name = 'aclApiService';
    }

    routePrivileges() {
        const headers = this.getHeaders();
        return this.httpClient.get('/_action/acl/route_privileges', { headers }).then((response) => {
            return Object.values(response.data);
        });
    }

    allPrivileges() {
        const headers = this.getHeaders();
        return this.httpClient.get('/_action/index', {}, { headers }).then((response) => {
            return Object.values(response.data);
        });
    }

    getHeaders() {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json'
        };
    }
}

export default AclApiService;
