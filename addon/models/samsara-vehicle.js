import Model, { attr, belongsTo } from '@ember-data/model';
import { computed } from '@ember/object';

export default class SamsaraVehicleModel extends Model {
    @attr('string') samsaraVehicleId;
    @attr('string') samsaraVehicleName;
    @attr('string') samsaraVehicleVin;
    @attr('string') samsaraVehicleSerial;
    @attr() samsaraVehicleData;
    @attr('date') lastSyncAt;
    @attr('string') syncStatus;
    @attr() meta;
    @attr('date') createdAt;
    @attr('date') updatedAt;

    @belongsTo('vehicle', { async: true, inverse: null }) vehicle;

    @computed('syncStatus')
    get syncStatusText() {
        const statusMap = {
            'pending': 'Pending',
            'syncing': 'Syncing',
            'active': 'Active',
            'failed': 'Failed',
            'disabled': 'Disabled'
        };
        return statusMap[this.syncStatus] || 'Unknown';
    }

    @computed('syncStatus')
    get syncStatusClass() {
        const classMap = {
            'pending': 'text-yellow-600 bg-yellow-100',
            'syncing': 'text-blue-600 bg-blue-100',
            'active': 'text-green-600 bg-green-100',
            'failed': 'text-red-600 bg-red-100',
            'disabled': 'text-gray-600 bg-gray-100'
        };
        return classMap[this.syncStatus] || 'text-gray-600 bg-gray-100';
    }

    @computed('vehicle')
    get isLinked() {
        return !!this.vehicle;
    }

    @computed('isLinked')
    get linkStatusText() {
        return this.isLinked ? 'Linked' : 'Not Linked';
    }

    @computed('isLinked')
    get linkStatusClass() {
        return this.isLinked ? 'text-green-600' : 'text-gray-500';
    }

    @computed('samsaraVehicleName', 'samsaraVehicleId')
    get displayName() {
        return this.samsaraVehicleName || `Vehicle ${this.samsaraVehicleId}`;
    }

    @computed('samsaraVehicleData')
    get lastLocation() {
        const data = this.samsaraVehicleData;
        if (data && data.location) {
            return {
                latitude: data.location.latitude,
                longitude: data.location.longitude,
                timestamp: data.location.time,
                speed: data.location.speed,
                heading: data.location.heading
            };
        }
        return null;
    }

    @computed('lastLocation')
    get hasLocation() {
        return !!this.lastLocation;
    }

    @computed('lastLocation.latitude', 'lastLocation.longitude')
    get locationText() {
        if (this.hasLocation) {
            const lat = this.lastLocation.latitude.toFixed(6);
            const lng = this.lastLocation.longitude.toFixed(6);
            return `${lat}, ${lng}`;
        }
        return 'No location data';
    }

    @computed('lastSyncAt')
    get lastSyncText() {
        if (!this.lastSyncAt) {
            return 'Never synced';
        }
        return this.lastSyncAt;
    }

    @computed('syncStatus')
    get canSync() {
        return ['pending', 'active', 'failed'].includes(this.syncStatus);
    }

    @computed('syncStatus')
    get isSyncing() {
        return this.syncStatus === 'syncing';
    }

    @computed('syncStatus')
    get isActive() {
        return this.syncStatus === 'active';
    }

    @computed('syncStatus')
    get hasFailed() {
        return this.syncStatus === 'failed';
    }

    @computed('meta')
    get lastSyncError() {
        return this.meta?.last_sync_error;
    }

    @computed('samsaraVehicleVin')
    get displayVin() {
        return this.samsaraVehicleVin || 'No VIN';
    }

    @computed('samsaraVehicleSerial')
    get displaySerial() {
        return this.samsaraVehicleSerial || 'No Serial';
    }

    @computed('vehicle.name')
    get linkedVehicleName() {
        return this.vehicle?.name || 'Not linked';
    }
}

