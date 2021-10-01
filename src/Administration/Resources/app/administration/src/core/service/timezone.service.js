export default class TimezoneService {
    /**
     * Returns an array of all timezones in the world
     * @returns {Promise<string[]>}
     */
    async loadTimezones() {
        const timezonesImport = await import('@vvo/tzdb/time-zones-names.json');

        return timezonesImport.default;
    }
}
