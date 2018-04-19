export default function MediaService(client) {
    return {
        readAllMedia,
        readAllMediaAsPaginatedList,
        readMediaById
    };

    function readAllMedia() {
        const mediaList = {};

        return client.get('/media').then((response) => {
            mediaList.media = response.data.data;
            mediaList.totalMedia = response.data.total;

            return mediaList;
        });
    }

    function readAllMediaAsPaginatedList(limit = 25, offset = 0) {
        const mediaList = {};

        return client.get(`/media?limit=${limit}&start=${offset}`).then((response) => {
            mediaList.media = response.data.data;
            mediaList.totalMedia = response.data.total;

            return mediaList;
        });
    }

    function readMediaById(id) {
        let media = {};

        if (!id) {
            return Promise.reject(new Error('"id" argument needs to be provided'));
        }

        return client.get(`/media/${id}`).then((response) => {
            media = response.data.data;

            return media;
        });
    }
}
