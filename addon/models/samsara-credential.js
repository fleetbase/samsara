import Model, { attr, hasMany } from '@ember-data/model';
import { computed } from '@ember/object';

export default class SamsaraCredentialModel extends Model {
    @attr('string') name;
    @attr('string') api_token;
    @attr('string') api_base_url;
    @attr('string') webhook_url;
    @attr('string') webhook_secret;
    @attr('boolean') is_active;
    @attr('boolean') is_sandbox;
    @attr('number') sync_interval;
    @attr('date') last_sync_at;
    @attr('raw') meta;
    @attr('date') created_at;
    @attr('date') updated_at;

    @hasMany('samsara-vehicle') vehicles;

    @computed('is_active')
    get statusText() {
        return this.is_active ? 'Active' : 'Inactive';
    }

    @computed('is_sandbox')
    get environmentText() {
        return this.is_sandbox ? 'Sandbox' : 'Production';
    }

    @computed('last_sync_at')
    get lastSyncText() {
        if (!this.last_sync_at) {
            return 'Never';
        }
        return this.last_sync_at;
    }

    @computed('last_sync_at', 'sync_interval')
    get syncIntervalText() {
        const interval = this.sync_interval || 5;
        return `${interval} minute${interval !== 1 ? 's' : ''}`;
    }

    @computed('api_base_url')
    get displayApiUrl() {
        return this.api_base_url || 'https://api.samsara.com';
    }

    @computed('webhook_url')
    get hasWebhook() {
        return !!this.webhook_url;
    }

    @computed('api_token')
    get hasApiToken() {
        return !!this.api_token;
    }

    @computed('hasApiToken', 'is_active')
    get isConfigured() {
        return this.hasApiToken && this.is_active;
    }

    @computed('name', 'environmentText')
    get displayName() {
        return `${this.name} (${this.environmentText})`;
    }
}
