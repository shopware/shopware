/**
 * @private
 */
export default class UserActivityService {
    updateLastUserActivity(date?: Date): void {
        if (date === undefined) {
            date = new Date();
        }

        Shopware.Context.app.lastActivity = Math.round(+date / 1000);
    }
}
