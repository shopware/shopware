/**
 * @private
 * @package admin
 */
export default class UserActivityService {
    getLastUserActivity(): Date {
        const lastActivity = localStorage.getItem('lastActivity');

        if (!lastActivity) {
            return new Date();
        }

        return new Date(+lastActivity);
    }

    updateLastUserActivity(date?: Date): void {
        const lastActivity = date?.getTime() ?? Date.now();

        localStorage.setItem('lastActivity', `${lastActivity}`);
    }
}
