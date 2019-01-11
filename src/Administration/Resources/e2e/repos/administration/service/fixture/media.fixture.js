const FixtureService = require('administration/service/fixture.service.js').default;

export default class MediaFixtureService extends FixtureService {
    constructor() {
        super();

        this.mediaFolderFixture = this.loadJson('media-folder.json');
        this.mediaConfigurationFixture = this.loadJson('media-configuration.json');
    }

    setMediaFolderFixture(json) {
        this.mediaFolderFixture = json;
    }


    setMediaConfiguationFixture(json) {
        this.mediaConfigurationFixture = json;
    }

    setFolderFixture(userData) {
        console.log('### Set media folder fixtures...');

        const mediaFolderJson = this.mediaFolderFixture;
        const mediaConfigurationJson = this.mediaConfigurationFixture;
        let confId = '';
        let finalMediaFolderData = {};

        return this.apiClient.post('/v1/media-folder-configuration?_response=true', mediaConfigurationJson)
            .then((result) => {
                confId = result.id;
            }).then(() => {
                finalMediaFolderData = this.mergeFixtureWithData({
                    configurationId: confId
                }, mediaFolderJson, userData);
            }).then(() => {
                return this.apiClient.post('/v1/media-folder?_response=true', finalMediaFolderData)
            }).catch((err) => {
                console.log('• ✖ - Error: ', err);
            }).then((result) => {
                console.log(`• ✓ - Created: ${result.id}`);
            });
    }
}

global.MediaFixtureService = new MediaFixtureService();
