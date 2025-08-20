import Model, { attr, belongsTo } from '@ember-data/model';
import { computed } from '@ember/object';

export default class SamsaraWebhookEventModel extends Model {
    @attr('string') eventId;
    @attr('string') eventType;
    @attr() eventData;
    @attr('date') processedAt;
    @attr('string') processingStatus;
    @attr('string') errorMessage;
    @attr() meta;
    @attr('date') createdAt;
    @attr('date') updatedAt;

    @belongsTo('samsara-vehicle', { async: true, inverse: null }) samsaraVehicle;

    @computed('processingStatus')
    get processingStatusText() {
        const statusMap = {
            'pending': 'Pending',
            'processing': 'Processing',
            'processed': 'Processed',
            'failed': 'Failed'
        };
        return statusMap[this.processingStatus] || 'Unknown';
    }

    @computed('processingStatus')
    get processingStatusClass() {
        const classMap = {
            'pending': 'text-yellow-600 bg-yellow-100',
            'processing': 'text-blue-600 bg-blue-100',
            'processed': 'text-green-600 bg-green-100',
            'failed': 'text-red-600 bg-red-100'
        };
        return classMap[this.processingStatus] || 'text-gray-600 bg-gray-100';
    }

    @computed('eventType')
    get eventTypeText() {
        const typeMap = {
            'Alert': 'Alert',
            'VehicleLocationUpdate': 'Location Update',
            'location': 'Location Update',
            'VehicleUpdate': 'Vehicle Update',
            'vehicle': 'Vehicle Update'
        };
        return typeMap[this.eventType] || this.eventType;
    }

    @computed('eventType')
    get eventTypeIcon() {
        const iconMap = {
            'Alert': 'exclamation-triangle',
            'VehicleLocationUpdate': 'map-marker-alt',
            'location': 'map-marker-alt',
            'VehicleUpdate': 'car',
            'vehicle': 'car'
        };
        return iconMap[this.eventType] || 'bell';
    }

    @computed('eventType')
    get eventTypeClass() {
        const classMap = {
            'Alert': 'text-red-600',
            'VehicleLocationUpdate': 'text-blue-600',
            'location': 'text-blue-600',
            'VehicleUpdate': 'text-green-600',
            'vehicle': 'text-green-600'
        };
        return classMap[this.eventType] || 'text-gray-600';
    }

    @computed('processingStatus')
    get isProcessed() {
        return this.processingStatus === 'processed';
    }

    @computed('processingStatus')
    get isPending() {
        return this.processingStatus === 'pending';
    }

    @computed('processingStatus')
    get isFailed() {
        return this.processingStatus === 'failed';
    }

    @computed('processingStatus')
    get isProcessing() {
        return this.processingStatus === 'processing';
    }

    @computed('isFailed', 'isPending')
    get canRetry() {
        return this.isFailed || this.isPending;
    }

    @computed('eventData')
    get vehicleInfo() {
        const data = this.eventData;
        if (data && data.event && data.event.device) {
            return {
                id: data.event.device.id,
                name: data.event.device.name,
                vin: data.event.device.vin,
                serial: data.event.device.serial
            };
        }
        if (data && data.vehicle) {
            return {
                id: data.vehicle.id,
                name: data.vehicle.name,
                vin: data.vehicle.vin,
                serial: data.vehicle.serial
            };
        }
        return null;
    }

    @computed('vehicleInfo')
    get vehicleName() {
        return this.vehicleInfo?.name || 'Unknown Vehicle';
    }

    @computed('eventData')
    get locationData() {
        const data = this.eventData;
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

    @computed('locationData')
    get hasLocationData() {
        return !!this.locationData;
    }

    @computed('eventData')
    get alertInfo() {
        const data = this.eventData;
        if (data && data.event && this.eventType === 'Alert') {
            return {
                condition: data.event.alertConditionDescription,
                details: data.event.details,
                summary: data.event.summary,
                resolved: data.event.resolved
            };
        }
        return null;
    }

    @computed('alertInfo')
    get hasAlertInfo() {
        return !!this.alertInfo;
    }

    @computed('errorMessage')
    get hasError() {
        return !!this.errorMessage;
    }

    @computed('processedAt')
    get processedAtText() {
        if (!this.processedAt) {
            return 'Not processed';
        }
        return this.processedAt;
    }

    @computed('eventId', 'eventType', 'createdAt')
    get displayTitle() {
        const timestamp = this.createdAt ? this.createdAt.toLocaleString() : 'Unknown time';
        return `${this.eventTypeText} - ${timestamp}`;
    }
}

