export default function ProductManufacturerService(client) {
    return {
        readAll,
        readByUuid
    };

    function readAll(limit = 25, offset = 0) {
        return client.get(`productManufacturer.json?limit=${limit}&offset=${offset}`).then((response) => {
            console.log(response);
        });
    }

    function readByUuid() {
        console.log('readByUuid', client);
    }
}
