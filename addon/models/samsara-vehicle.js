import Model, { attr, belongsTo } from '@ember-data/model';
import { computed } from '@ember/object';

export default class SamsaraVehicleModel extends Model {
    @attr('string') samsara_vehicle_id;
    @attr('string') name;
    @attr('string') vin;
    @attr('string') serial;
    @attr('string') model;
    @attr('string') make;
    @attr('string') year;
    @attr('string') license_plate;
    @attr('string') vehicle_type;
    @attr('string') sync_status;
    @attr('raw') meta;
    @attr('raw') data;
    @attr('raw') last_location;
    @attr('date') last_sync_at;
    @attr('date') created_at;
    @attr('date') updated_at;

    @belongsTo('vehicle') vehicle;

    @computed('syncStatus', 'sync_status')
    get syncStatusText() {
        const statusMap = {
            pending: 'Pending',
            syncing: 'Syncing',
            active: 'Active',
            failed: 'Failed',
            disabled: 'Disabled',
        };
        return statusMap[this.syncStatus] || 'Unknown';
    }

    @computed('vehicle')
    get isLinked() {
        return !!this.vehicle;
    }

    @computed('name', 'samsara_vehicle_id')
    get displayName() {
        return this.name || `Vehicle ${this.samsara_vehicle_id}`;
    }

    @computed('last_location', 'last_location.{latitude,longitude}')
    get locationText() {
        if (this.last_location) {
            const lat = this.last_location.latitude.toFixed(6);
            const lng = this.last_location.longitude.toFixed(6);
            return `${lat}, ${lng}`;
        }
        return 'No location data';
    }

    @computed('last_sync_at')
    get lastSyncText() {
        if (!this.last_sync_at) {
            return 'Never synced';
        }
        return this.last_sync_at;
    }

    @computed('sync_status')
    get canSync() {
        return ['pending', 'active', 'failed'].includes(this.sync_status);
    }

    @computed('sync_status')
    get isSyncing() {
        return this.sync_status === 'syncing';
    }

    @computed('sync_status')
    get isActive() {
        return this.sync_status === 'active';
    }

    @computed('sync_status')
    get hasFailed() {
        return this.sync_status === 'failed';
    }

    @computed('meta.last_sync_error')
    get lastSyncError() {
        return this.meta?.last_sync_error;
    }

    @computed('vin')
    get displayVin() {
        return this.vin || 'No VIN';
    }

    @computed('serial')
    get displaySerial() {
        return this.serial || 'No Serial';
    }

    @computed('vehicle.name')
    get linkedVehicleName() {
        return this.vehicle?.name || 'Not linked';
    }
}
