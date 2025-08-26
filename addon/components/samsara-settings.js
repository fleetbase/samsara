import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { debug } from '@ember/debug';
import { task } from 'ember-concurrency';
import { all } from 'rsvp';

export default class SamsaraSettingsComponent extends Component {
    @service store;
    @service fetch;
    @service notifications;
    @service hostRouter;
    @tracked apiCredentials = [];
    @tracked vehicles = [];
    @tracked vehiclesMeta = {};
    @tracked vehicleSearchQuery = '';

    constructor() {
        super(...arguments);
        this.loadApiCredentials.perform();
        this.loadSamsaraVehicles.perform();
    }

    @task *loadApiCredentials() {
        try {
            const apiCredentials = yield this.store.findAll('samsara-credential');
            this.apiCredentials = Array.from(apiCredentials);
        } catch (err) {
            debug('[Samsara] Unable to load Samsara API Credentials: ' + err.message);
        }
    }

    @task *loadSamsaraVehicles(params = {}) {
        try {
            const vehicles = yield this.store.query('samsara-vehicle', params);
            this.vehicles = Array.from(vehicles);
            this.vehiclesMeta = vehicles.meta;
        } catch (err) {
            debug('[Samsara] Unable to load Samsara Vehicles: ' + err.message);
        }
    }

    @task *saveApiCredentials() {
        try {
            yield all(this.apiCredentials.map((c) => c.save()));
            this.notifications.success('Samsara credentials saved.');
        } catch (err) {
            this.notifications.serverError(err);
            debug('[Samsara] Faile to save Samsara API Credentials: ' + err.message);
        }
    }

    @task *deleteApiCredential(samsaraCredential) {
        try {
            yield samsaraCredential.destroyRecord();
            this.notifications.success('Samsara credentials saved.');
        } catch (err) {
            this.notifications.serverError(err);
            debug('[Samsara] Faile to delete Samsara API Credentials: ' + err.message);
        }
    }

    @task *testCredentialConnection(samsaraCredential) {
        try {
            const result = yield this.fetch.post(`credentials/${samsaraCredential.id}/test`, {}, { namespace: 'samsara/int/v1' });
            this.notifications.success('Samsara connection successful.');
            console.log('[result]', result);
        } catch (err) {
            this.notifications.serverError(err);
            debug('[Samsara] Failed to test connection Samsara API Credential: ' + err.message);
        }
    }

    @task *runSync(samsaraCredential) {
        try {
            const result = yield this.fetch.post(`credentials/${samsaraCredential.id}/sync`, {}, { namespace: 'samsara/int/v1' });
            this.notifications.success('Samsara sync started successfully.');
            console.log('[result]', result);
        } catch (err) {
            this.notifications.serverError(err);
            debug('[Samsara] Failed to run sync for Samsara API Credential: ' + err.message);
        }
    }

    @action createNewCredential() {
        try {
            const newApiCredential = this.store.createRecord('samsara-credential', {
                name: 'New API Credential',
                api_base_url: 'https://api.samsara.com',
                sync_interval: 5,
            });
            this.apiCredentials = [...this.apiCredentials, newApiCredential];
        } catch (err) {
            debug('[Samsara] Failed to create new API Credential: ' + err.message);
        }
    }

    @action changeVehiclesPage(page = 1) {
        this.loadSamsaraVehicles.perform({ page });
    }

    @action searchVehicles() {
        this.loadSamsaraVehicles.perform({ page: 1, search: this.vehicleSearchQuery });
    }

    @action viewFleetOpsVehicle(fleetopsVehicle) {
        return this.hostRouter.transitionTo('console.fleet-ops.management.vehicles.index.details', fleetopsVehicle);
    }
}
