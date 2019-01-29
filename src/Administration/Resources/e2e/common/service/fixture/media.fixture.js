const FixtureService = require('./../fixture.service.js').default;

export default class MediaFixtureService extends FixtureService {
    constructor() {
        super();

        this.mediaFolderFixture = this.loadJson('media-folder.json');
    }

    setMediaFolderFixture(json) {
        this.mediaFolderFixture = json;
    }

    setFolderFixture(userData) {
        global.logger.lineBreak();
        global.logger.title('Set media folder fixtures...');

        const mediaFolderJson = this.mediaFolderFixture;

        let finalData = this.mergeFixtureWithData(mediaFolderJson, userData);

        return this.apiClient.post('/v1/media-folder?_response=true', finalData)
            .then((result) => {
                global.logger.success(result.id);
                global.logger.lineBreak();
            }).catch((err) => {
                global.logger.error(err);
                global.logger.lineBreak();
            });
    }
}

global.MediaFixtureService = new MediaFixtureService();
