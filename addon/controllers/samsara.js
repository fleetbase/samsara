import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action, computed } from '@ember/object';
import { task } from 'ember-concurrency';

export default class SamsaraController extends Controller {
    @service store;
    @service notifications;
    @service fetch;
    @service modalsManager;

    @tracked activeTab = 'vehicles';
    @tracked showCredentialModal = false;
    @tracked showVehicleLinkModal = false;
    @tracked showEventModal = false;
    @tracked editingCredential = null;
    @tracked linkingVehicle = null;
    @tracked viewingEvent = null;
    @tracked isSyncing = false;

    @computed('model.credentials.@each.isActive')
    get activeCredential() {
        return this.model.credentials.find(cred => cred.isActive);
    }

    @computed('activeCredential')
    get hasActiveCredential() {
        return !!this.activeCredential;
    }

    @computed('model.vehicles.length')
    get totalVehicles() {
        return this.model.vehicles.length;
    }

    @computed('model.vehicles.@each.syncStatus')
    get activeSyncs() {
        return this.model.vehicles.filter(v => v.syncStatus === 'active').length;
    }

    @computed('activeCredential.lastSyncAt')
    get lastSyncTime() {
        return this.activeCredential?.lastSyncAt;
    }

    @computed('model.webhookEvents.length')
    get recentEventsCount() {
        return this.model.webhookEvents.length;
    }

    @computed('store')
    get fleetOpsVehicles() {
        return this.store.findAll('vehicle');
    }

    @action
    setActiveTab(tab) {
        this.activeTab = tab;
    }

    @action
    openCredentialModal(credential = null) {
        this.editingCredential = credential || this.store.createRecord('samsara-credential');
        this.showCredentialModal = true;
    }

    @action
    closeCredentialModal() {
        this.showCredentialModal = false;
        this.editingCredential = null;
    }

    @action
    async saveCredential(credential) {
        try {
            await credential.save();
            this.notifications.success('Samsara credential saved successfully');
            this.closeCredentialModal();
            
            // Refresh credentials
            this.model.credentials.reload();
        } catch (error) {
            this.notifications.error('Failed to save credential: ' + error.message);
        }
    }

    @action
    async deleteCredential(credential) {
        if (confirm('Are you sure you want to delete this credential?')) {
            try {
                await credential.destroyRecord();
                this.notifications.success('Credential deleted successfully');
            } catch (error) {
                this.notifications.error('Failed to delete credential: ' + error.message);
            }
        }
    }

    @action
    async testCredential(credential) {
        try {
            const response = await this.fetch.request(`/samsara/credentials/${credential.id}/test`, {
                method: 'POST'
            });
            
            if (response.success) {
                this.notifications.success('Connection test successful');
            } else {
                this.notifications.error('Connection test failed: ' + response.message);
            }
        } catch (error) {
            this.notifications.error('Connection test failed: ' + error.message);
        }
    }

    @action
    async activateCredential(credential) {
        try {
            await this.fetch.request(`/samsara/credentials/${credential.id}/activate`, {
                method: 'POST'
            });
            
            this.notifications.success('Credential activated successfully');
            this.model.credentials.reload();
        } catch (error) {
            this.notifications.error('Failed to activate credential: ' + error.message);
        }
    }

    @task
    *syncAllVehicles() {
        try {
            this.isSyncing = true;
            
            const response = yield this.fetch.request('/samsara/vehicles/sync-all', {
                method: 'POST'
            });
            
            this.notifications.success(`Sync completed: ${response.result.created} created, ${response.result.updated} updated`);
            
            // Refresh vehicles
            this.model.vehicles.reload();
        } catch (error) {
            this.notifications.error('Sync failed: ' + error.message);
        } finally {
            this.isSyncing = false;
        }
    }

    @action
    async syncVehicle(vehicle) {
        try {
            await this.fetch.request(`/samsara/vehicles/${vehicle.id}/sync`, {
                method: 'POST'
            });
            
            this.notifications.success('Vehicle synced successfully');
            vehicle.reload();
        } catch (error) {
            this.notifications.error('Vehicle sync failed: ' + error.message);
        }
    }

    @action
    linkVehicle(samsaraVehicle) {
        this.linkingVehicle = samsaraVehicle;
        this.showVehicleLinkModal = true;
    }

    @action
    closeLinkModal() {
        this.showVehicleLinkModal = false;
        this.linkingVehicle = null;
    }

    @action
    async saveLinkVehicle(samsaraVehicle, fleetOpsVehicle) {
        try {
            await this.fetch.request(`/samsara/vehicles/${samsaraVehicle.id}/link`, {
                method: 'POST',
                body: JSON.stringify({
                    vehicle_uuid: fleetOpsVehicle.id
                })
            });
            
            this.notifications.success('Vehicle linked successfully');
            this.closeLinkModal();
            samsaraVehicle.reload();
        } catch (error) {
            this.notifications.error('Failed to link vehicle: ' + error.message);
        }
    }

    @action
    async unlinkVehicle(samsaraVehicle) {
        if (confirm('Are you sure you want to unlink this vehicle?')) {
            try {
                await this.fetch.request(`/samsara/vehicles/${samsaraVehicle.id}/unlink`, {
                    method: 'POST'
                });
                
                this.notifications.success('Vehicle unlinked successfully');
                samsaraVehicle.reload();
            } catch (error) {
                this.notifications.error('Failed to unlink vehicle: ' + error.message);
            }
        }
    }

    @action
    async deleteVehicle(vehicle) {
        if (confirm('Are you sure you want to delete this vehicle sync?')) {
            try {
                await vehicle.destroyRecord();
                this.notifications.success('Vehicle sync deleted successfully');
            } catch (error) {
                this.notifications.error('Failed to delete vehicle: ' + error.message);
            }
        }
    }

    @action
    viewEvent(event) {
        this.viewingEvent = event;
        this.showEventModal = true;
    }

    @action
    closeEventModal() {
        this.showEventModal = false;
        this.viewingEvent = null;
    }

    @action
    async retryEvent(event) {
        try {
            await this.fetch.request(`/samsara/webhook-events/${event.id}/retry`, {
                method: 'POST'
            });
            
            this.notifications.success('Event retry initiated');
            event.reload();
        } catch (error) {
            this.notifications.error('Failed to retry event: ' + error.message);
        }
    }

    @action
    editCredential(credential) {
        this.openCredentialModal(credential);
    }

    @action
    saveSettings(settings) {
        // Handle settings save
        this.notifications.success('Settings saved successfully');
    }
}

