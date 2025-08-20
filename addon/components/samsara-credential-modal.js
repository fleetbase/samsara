import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

export default class SamsaraCredentialModalComponent extends Component {
    @service notifications;
    @service fetch;

    @tracked isTestingConnection = false;
    @tracked connectionTestResult = null;

    get isEditing() {
        return this.args.credential && this.args.credential.id;
    }

    get modalTitle() {
        return this.isEditing ? 'Edit Samsara Credential' : 'Add Samsara Credential';
    }

    get credential() {
        return this.args.credential;
    }

    @action
    updateField(field, event) {
        const value = event.target.type === 'checkbox' ? event.target.checked : event.target.value;
        this.credential.set(field, value);
    }

    @action
    async testConnection() {
        if (!this.credential.apiToken) {
            this.notifications.error('Please enter an API token first');
            return;
        }

        this.isTestingConnection = true;
        this.connectionTestResult = null;

        try {
            const response = await this.fetch.request('/samsara/credentials/test', {
                method: 'POST',
                body: JSON.stringify({
                    api_token: this.credential.apiToken,
                    api_base_url: this.credential.apiBaseUrl || 'https://api.samsara.com'
                })
            });

            this.connectionTestResult = {
                success: response.success,
                message: response.message
            };

            if (response.success) {
                this.notifications.success('Connection test successful!');
            } else {
                this.notifications.error('Connection test failed: ' + response.message);
            }
        } catch (error) {
            this.connectionTestResult = {
                success: false,
                message: error.message
            };
            this.notifications.error('Connection test failed: ' + error.message);
        } finally {
            this.isTestingConnection = false;
        }
    }

    @action
    async save() {
        try {
            // Validate required fields
            if (!this.credential.name) {
                this.notifications.error('Please enter a credential name');
                return;
            }

            if (!this.credential.apiToken) {
                this.notifications.error('Please enter an API token');
                return;
            }

            // Set defaults
            if (!this.credential.apiBaseUrl) {
                this.credential.set('apiBaseUrl', 'https://api.samsara.com');
            }

            if (!this.credential.syncInterval) {
                this.credential.set('syncInterval', 5);
            }

            await this.args.onSave(this.credential);
        } catch (error) {
            this.notifications.error('Failed to save credential: ' + error.message);
        }
    }

    @action
    cancel() {
        this.args.onCancel();
    }

    @action
    generateWebhookSecret() {
        // Generate a random webhook secret
        const secret = Array.from(crypto.getRandomValues(new Uint8Array(32)))
            .map(b => b.toString(16).padStart(2, '0'))
            .join('');
        
        this.credential.set('webhookSecret', secret);
        this.notifications.success('Webhook secret generated');
    }
}

