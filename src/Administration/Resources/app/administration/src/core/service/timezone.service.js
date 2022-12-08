/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default class TimezoneService {
    /**
     * @package admin
     *
     * Returns an array of all timezones in the world
     * @returns {Promise<string[]>}
     */
    async loadTimezones() {
        const timezonesImport = await import('@vvo/tzdb/time-zones-names.json');

        return timezonesImport.default;
    }
}
