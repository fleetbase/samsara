<p align="center">
    <p align="center">
        <img src="https://github.com/user-attachments/assets/afac09ee-1fbf-423b-b2a6-7f05a06b12b2" width="180" height="180" />
    </p>
    <p align="center">
       A comprehensive Fleetbase extension that integrates with the Samsara API for vehicle data import and real-time location tracking. This extension enables fleet operators to seamlessly sync vehicle data from Samsara into their Fleetbase instance, providing unified fleet management capabilities.
    </p>
</p>

---

## Overview

The Samsara Extension bridges the gap between Samsara's fleet management platform and Fleetbase's comprehensive logistics solution. It provides real-time vehicle data synchronization, location tracking, and event processing capabilities that enhance fleet visibility and operational efficiency.

* PHP 8.2 or above
* Ember.js v5.4 or above
* Ember CLI v5.4 or above
* Node.js v18 or above

### Key Benefits

- **Unified Fleet Management**: Manage both Samsara and non-Samsara vehicles within a single Fleetbase interface
- **Real-time Location Tracking**: Automatic location updates via Samsara webhooks
- **Comprehensive Vehicle Data**: Import detailed vehicle information including VIN, serial numbers, and metadata
- **Multi-tenant Architecture**: Secure company-level data isolation
- **Role-based Access Control**: Granular permissions for different user roles
- **Scalable Integration**: Handles high-volume webhook events and API calls

## Features

### Core Functionality

- **Vehicle Data Synchronization**: Automated import of vehicle information from Samsara
- **Real-time Location Updates**: Live vehicle tracking via webhook integration
- **Credential Management**: Secure storage and management of Samsara API credentials
- **Event Processing**: Comprehensive webhook event handling and logging
- **Multi-company Support**: Full multi-tenancy with company-level data isolation

### Advanced Features

- **Scheduled Synchronization**: Configurable sync intervals for vehicle data
- **Error Handling & Retry Logic**: Robust error handling with automatic retry mechanisms
- **Health Monitoring**: Built-in health checks and sync status monitoring
- **Audit Trail**: Complete logging of all sync operations and webhook events
- **Flexible Linking**: Link Samsara vehicles to existing FleetOps vehicles

## Structure

```
├── addon
├── app
├── assets
├── translations
├── config
├── node_modules
├── server
│ ├── config
│ ├── data
│ ├── migrations
│ ├── resources
│ ├── src
│ ├── tests
│ └── vendor
├── tests
├── testem.js
├── index.js
├── package.json
├── phpstan.neon.dist
├── phpunit.xml.dist
├── pnpm-lock.yaml
├── ember-cli-build.js
├── composer.json
├── CONTRIBUTING.md
├── LICENSE.md
├── README.md
```

## Installation

### Backend

Install the PHP packages using Composer:

```bash
composer require fleetbase/core-api
composer require fleetbase/samsara-api
```
### Frontend

Install the Ember.js Engine/Addon:

```bash
pnpm install @fleetbase/samsara-engine
```

## Configuration

### Environment Variables

Add the following environment variables to your `.env` file:

```env
# Samsara Extension Configuration
SAMSARA_DEFAULT_SYNC_INTERVAL=5
SAMSARA_WEBHOOK_TIMEOUT=30
SAMSARA_API_TIMEOUT=30
SAMSARA_MAX_RETRY_ATTEMPTS=3
```

### Samsara API Credentials

The extension requires Samsara API credentials to function. You can configure these through the Fleetbase console:

1. Navigate to **FleetOps > Settings > Samsara**
2. Click **Add Credential**
3. Fill in the required information:
   - **Name**: A descriptive name for the credential set
   - **API Token**: Your Samsara API token
   - **API Base URL**: Usually `https://api.samsara.com` (leave blank for default)
   - **Environment**: Select Sandbox or Production
   - **Sync Interval**: How often to sync vehicle data (1-60 minutes)

### Webhook Configuration

For real-time updates, configure webhooks in your Samsara dashboard:

1. **Webhook URL**: `https://api.fleetbase.io/int/v1/samsara/webhook/{companyId}`
2. **Events**: Select the events you want to receive:
   - Vehicle Location Updates
   - Vehicle Information Changes
   - Alert Events
3. **Secret**: Generate a webhook secret for signature verification

## Usage

### Basic Vehicle Synchronization

Once configured, the extension automatically synchronizes vehicle data from Samsara. You can also trigger manual synchronization:

1. Navigate to **FleetOps > Settings > Samsara > Vehicles**
2. Click **Sync All** to import all vehicles
3. Use the **Sync** button next to individual vehicles for selective updates

## Permissions & Security

The extension implements comprehensive role-based access control with the following permission structure:

### Permission Categories

#### Credential Permissions
- `samsara view credentials`: View API credentials
- `samsara create credentials`: Create new credentials
- `samsara update credentials`: Modify existing credentials
- `samsara delete credentials`: Remove credentials
- `samsara test credentials`: Test API connections
- `samsara manage credentials`: Activate/deactivate credentials

#### Vehicle Permissions
- `samsara view vehicles`: View vehicle data and sync status
- `samsara create vehicles`: Create new vehicle syncs
- `samsara update vehicles`: Modify vehicle sync settings
- `samsara delete vehicles`: Remove vehicle syncs
- `samsara sync vehicles`: Trigger manual synchronization
- `samsara link vehicles`: Link/unlink vehicles to FleetOps
- `samsara view location history`: Access vehicle location history

#### Webhook Permissions
- `samsara view webhook events`: View webhook events and logs
- `samsara manage webhook events`: Retry events and manage settings

#### Administrative Permissions
- `samsara view sync status`: View synchronization status
- `samsara manage sync`: Trigger syncs and manage settings
- `samsara admin`: Full administrative access

### Predefined Roles

#### Samsara Viewer
Basic read-only access to view vehicles, credentials, and events.

#### Samsara Operator
Operational access including vehicle syncing and linking capabilities.

#### Samsara Manager
Management access including credential management and webhook configuration.

#### Samsara Administrator
Full administrative access to all extension features.

## Usage

### Backend

🧹 Keep a modern codebase with **PHP CS Fixer**:
```bash
composer lint
```

⚗️ Run static analysis using **PHPStan**:
```bash
composer test:types
```

✅ Run unit tests using **PEST**
```bash
composer test:unit
```

🚀 Run the entire test suite:
```bash
composer test
```

### Frontend

🧹 Keep a modern codebase with **ESLint**:
```bash
pnpm lint
```

✅ Run unit tests using **Ember/QUnit**
```bash
pnpm test
pnpm test:ember
pnpm test:ember-compatibility
```

🚀 Start the Ember Addon/Engine
```bash
pnpm start
```

🔨 Build the Ember Addon/Engine
```bash
pnpm build
```

## Contributing
See the Contributing Guide for details on how to contribute to this project.

## License

This project is licensed under the AGPL-3.0-or-later License. See the [LICENSE](LICENSE.md) file for details.

---

**Developed by Fleetbase**

For support, please contact [support@fleetbase.io](mailto:support@fleetbase.io) or visit our [documentation](https://docs.fleetbase.io).
