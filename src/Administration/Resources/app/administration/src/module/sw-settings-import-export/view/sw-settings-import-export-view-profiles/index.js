import template from './sw-settings-import-export-view-profiles.html.twig';
import './sw-settings-import-export-view-profiles.scss';

Shopware.Component.register('sw-settings-import-export-view-profiles', {
    template,

    inject: ['repositoryFactory'],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    data() {
        return {
            isLoading: false,
            selectedProfile: null,
            profiles: null,
            searchTerm: null
        };
    },

    computed: {
        profileRepository() {
            // TODO: change to profile entity
            return this.repositoryFactory.create('product');
        },

        profileCriteria() {
            const criteria = new Shopware.Data.Criteria();

            // TODO: here you can change the criteria for fetching the activites
            criteria.setPage(1);
            criteria.setTerm(this.searchTerm);

            return criteria;
        },

        profilesColumns() {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: 'Name', // TODO: change label to snippet path
                    // routerLink: 'sw.order.detail',
                    allowResize: true,
                    primary: true
                },
                {
                    property: 'id',
                    dataIndex: 'id',
                    label: 'Id', // TODO: change label to snippet path
                    allowResize: true,
                    primary: false
                },
                {
                    property: 'description',
                    dataIndex: 'description',
                    label: 'Description', // TODO: change label to snippet path
                    // label: '',
                    allowResize: true,
                    primary: false
                },
                {
                    property: 'stock',
                    dataIndex: 'stock',
                    label: 'Stock', // TODO: change label to snippet path
                    allowResize: true,
                    primary: false
                }
            ];
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadProfiles();
            // TODO: only for easier development
            // this.selectedProfile = this.mockNewProfile();
        },

        async loadProfiles() {
            this.isLoading = true;

            this.profiles = await this.profileRepository.search(this.profileCriteria, Shopware.Context.api);

            this.isLoading = false;
        },

        // TODO: remove mock
        mockNewProfile() {
            const profileMock = this.profileRepository.create();

            profileMock.name = '';
            profileMock.objectType = ''; // TODO: naming is not save
            profileMock.identifier = ''; // TODO: naming is not save
            profileMock.delimiter = '';
            profileMock.enclosure = '';
            profileMock.format = '';
            profileMock.total = '';
            profileMock.file = null;

            return profileMock;
        },

        // TODO: remove mock
        async mockExistingProfile() {
            const profiles = await this.profileRepository.search(new Shopware.Data.Criteria(1, 1), Shopware.Context.api);
            const profileMock = profiles.first();

            profileMock.name = '';
            profileMock.objectType = ''; // TODO: naming is not save
            profileMock.identifier = ''; // TODO: naming is not save
            profileMock.delimiter = '';
            profileMock.enclosure = '';
            profileMock.format = '';
            profileMock.total = '';
            profileMock.file = null;

            return profileMock;
        },

        onSearch() {
            this.loadProfiles();
        },

        onAddNewProfile() {
            // TODO: change mock to real data
            this.selectedProfile = this.mockNewProfile();
        },

        async onEditProfile(id) {
            // TODO: change mock to real data
            this.selectedProfile = await this.mockExistingProfile();
        },

        onDuplicateProfile(id) {
            // TODO: add functionality
            console.log('Duplicate profile');
        },

        onDeleteProfile(id) {
            // TODO: add functionality
            console.log('Delete profile');
        },

        closeSelectedProfile() {
            this.selectedProfile = null;
        },

        saveSelectedProfile() {
            // TODO: add save
            console.log('Save', this.selectedProfile);
            this.selectedProfile = null;
        }
    }
});
