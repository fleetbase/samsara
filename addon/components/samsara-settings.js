import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { debug } from '@ember/debug';
import { task } from 'ember-concurrency';
import { all } from 'rsvp';

export default class SamsaraSettingsComponent extends Component {
    @service store;
    @service notifications;
    @tracked apiCredentials = [];

    constructor() {
        super(...arguments);
        this.loadApiCredentials.perform();
    }

    @task *loadApiCredentials() {
        try {
            const apiCredentials = yield this.store.findAll('samsara-credential');
            this.apiCredentials = Array.from(apiCredentials);
        } catch (err) {
            debug('[Samsara] Unable to load Samsara API Credentials: ' + err.message);
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
}
