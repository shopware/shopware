export default function ManufacturerService(client) {
    return {
        readAllManufacturers,
        readAllManufacturersAsPaginatedList,
        readManufacturerById
    };

    function readAllManufacturers() {
        const manufacturersList = {};

        return client.get('/manufacturers').then((response) => {
            manufacturersList.manufacturers = response.data.data;
            manufacturersList.totalManufacturers = response.data.total;

            return manufacturersList;
        });
    }

    function readAllManufacturersAsPaginatedList(limit = 25, offset = 0) {
        const manufacturersList = {};

        return client.get(`/manufacturers?limit=${limit}&start=${offset}`).then((response) => {
            manufacturersList.manufacturers = response.data.data;
            manufacturersList.totalManufacturers = response.data.total;

            return manufacturersList;
        });
    }

    function readManufacturerById(id) {
        let manufacturer = {};

        if (!id) {
            return Promise.reject(new Error('"id" argument needs to be provided'));
        }

        return client.get(`/manufacturers/${id}`).then((response) => {
            manufacturer = response.data.data;

            return manufacturer;
        });
    }
}
