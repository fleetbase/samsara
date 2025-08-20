import Model, { attr, hasMany } from '@ember-data/model';
import { computed } from '@ember/object';

export default class SamsaraCredentialModel extends Model {
    @attr('string') name;
    @attr('string') apiToken;
    @attr('string') apiBaseUrl;
    @attr('string') webhookUrl;
    @attr('string') webhookSecret;
    @attr('boolean') isActive;
    @attr('boolean') isSandbox;
    @attr('number') syncInterval;
    @attr('date') lastSyncAt;
    @attr() meta;
    @attr('date') createdAt;
    @attr('date') updatedAt;

    @hasMany('samsara-vehicle') vehicles;

    @computed('isActive')
    get statusText() {
        return this.isActive ? 'Active' : 'Inactive';
    }

    @computed('isActive')
    get statusClass() {
        return this.isActive ? 'text-green-600' : 'text-gray-500';
    }

    @computed('isSandbox')
    get environmentText() {
        return this.isSandbox ? 'Sandbox' : 'Production';
    }

    @computed('isSandbox')
    get environmentClass() {
        return this.isSandbox ? 'text-yellow-600' : 'text-blue-600';
    }

    @computed('lastSyncAt')
    get lastSyncText() {
        if (!this.lastSyncAt) {
            return 'Never';
        }
        return this.lastSyncAt;
    }

    @computed('syncInterval')
    get syncIntervalText() {
        const interval = this.syncInterval || 5;
        return `${interval} minute${interval !== 1 ? 's' : ''}`;
    }

    @computed('apiBaseUrl')
    get displayApiUrl() {
        return this.apiBaseUrl || 'https://api.samsara.com';
    }

    @computed('webhookUrl')
    get hasWebhook() {
        return !!this.webhookUrl;
    }

    @computed('apiToken')
    get hasApiToken() {
        return !!this.apiToken;
    }

    @computed('hasApiToken', 'isActive')
    get isConfigured() {
        return this.hasApiToken && this.isActive;
    }

    @computed('name', 'environmentText')
    get displayName() {
        return `${this.name} (${this.environmentText})`;
    }
}
