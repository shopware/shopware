import ApiService from '../api.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class NotificationsService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, null, 'application/json');
        this.name = 'notificationsService';
    }

    fetchNotifications(limit, latestTimestamp = null) {
        return this.httpClient
            .get('notification/message', {
                params: { limit, latestTimestamp },
                headers: this.getBasicHeaders(),
            })
            .then(({ data }) => {
                return data;
            });
    }
}
