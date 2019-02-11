const AdminFixtureService = require('../fixture.service.js').default;

export default class MediaFixtureService extends AdminFixtureService {
    constructor() {
        super();

        this.mediaFolderFixture = this.loadJson('media-folder.json');
    }

    setMediaFolderFixture(json) {
        this.mediaFolderFixture = json;
    }

    setFolderFixture(userData) {
        const startTime = new Date();
        global.logger.lineBreak();
        global.logger.title('Set media folder fixtures...');

        const mediaFolderJson = this.mediaFolderFixture;

        let finalData = this.mergeFixtureWithData(mediaFolderJson, userData);

        return this.apiClient.post('/v1/media-folder?_response=true', finalData)
            .then((data) => {
                const endTime = new Date() - startTime;
                global.logger.success(`${data.id} (${endTime / 1000}s)`);
                global.logger.lineBreak();
            }).catch((err) => {
                global.logger.error(err);
                global.logger.lineBreak();
            });
    }
}

global.MediaFixtureService = new MediaFixtureService();
