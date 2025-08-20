import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

export default class SamsaraVehiclesListComponent extends Component {
    @service store;
    @service notifications;

    @tracked searchTerm = '';
    @tracked statusFilter = 'all';
    @tracked linkFilter = 'all';

    get filteredVehicles() {
        let vehicles = this.args.vehicles || [];

        // Filter by search term
        if (this.searchTerm) {
            const term = this.searchTerm.toLowerCase();
            vehicles = vehicles.filter(
                (vehicle) =>
                    vehicle.displayName.toLowerCase().includes(term) ||
                    vehicle.samsaraVehicleId.toLowerCase().includes(term) ||
                    vehicle.samsaraVehicleVin?.toLowerCase().includes(term) ||
                    vehicle.linkedVehicleName.toLowerCase().includes(term)
            );
        }

        // Filter by sync status
        if (this.statusFilter !== 'all') {
            vehicles = vehicles.filter((vehicle) => vehicle.syncStatus === this.statusFilter);
        }

        // Filter by link status
        if (this.linkFilter !== 'all') {
            if (this.linkFilter === 'linked') {
                vehicles = vehicles.filter((vehicle) => vehicle.isLinked);
            } else if (this.linkFilter === 'unlinked') {
                vehicles = vehicles.filter((vehicle) => !vehicle.isLinked);
            }
        }

        return vehicles;
    }

    get statusOptions() {
        return [
            { value: 'all', label: 'All Statuses' },
            { value: 'active', label: 'Active' },
            { value: 'pending', label: 'Pending' },
            { value: 'failed', label: 'Failed' },
            { value: 'disabled', label: 'Disabled' },
        ];
    }

    get linkOptions() {
        return [
            { value: 'all', label: 'All Vehicles' },
            { value: 'linked', label: 'Linked Only' },
            { value: 'unlinked', label: 'Unlinked Only' },
        ];
    }

    @action
    updateSearchTerm(event) {
        this.searchTerm = event.target.value;
    }

    @action
    updateStatusFilter(value) {
        this.statusFilter = value;
    }

    @action
    updateLinkFilter(value) {
        this.linkFilter = value;
    }

    @action
    syncVehicle(vehicle) {
        this.args.onSync?.(vehicle);
    }

    @action
    linkVehicle(vehicle) {
        this.args.onLink?.(vehicle);
    }

    @action
    unlinkVehicle(vehicle) {
        this.args.onUnlink?.(vehicle);
    }

    @action
    deleteVehicle(vehicle) {
        this.args.onDelete?.(vehicle);
    }

    @action
    viewLocationHistory(vehicle) {
        // Navigate to location history view
        this.notifications.info('Location history feature coming soon');
    }
}
