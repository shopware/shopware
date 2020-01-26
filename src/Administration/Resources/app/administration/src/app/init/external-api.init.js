import Axios from 'axios';
import ExternalApiGravatarService from 'src/core/service/external/gravatar.service';

export default function initExternalApis() {
    this.addServiceProvider('ExternalApiGravatarService', () => new ExternalApiGravatarService(Axios.create()));
}
