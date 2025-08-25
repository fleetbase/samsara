<?php

namespace Fleetbase\Samsara\Console\Commands;

use Fleetbase\Models\Company;
use Fleetbase\Samsara\Models\SamsaraCredential;
use Fleetbase\Samsara\Services\SamsaraSyncService;
use Illuminate\Console\Command;

class SamsaraSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'samsara:sync 
                            {--company= : Specific company UUID to sync}
                            {--credential= : Specific credential UUID to use}
                            {--force : Force sync even if recently synced}
                            {--include-inactive : Include inactive vehicles in sync}
                            {--dry-run : Show what would be synced without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually trigger Samsara vehicle synchronization for one or all companies';

    /**
     * The sync service instance.
     */
    protected SamsaraSyncService $syncService;

    /**
     * Create a new command instance.
     */
    public function __construct(SamsaraSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting Samsara synchronization...');

        $companyUuid     = $this->option('company');
        $credentialUuid  = $this->option('credential');
        $force           = $this->option('force');
        $includeInactive = $this->option('include-inactive');
        $dryRun          = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        try {
            if ($companyUuid) {
                return $this->syncSpecificCompany($companyUuid, $credentialUuid, $force, $includeInactive, $dryRun);
            } else {
                return $this->syncAllCompanies($force, $includeInactive, $dryRun);
            }
        } catch (\Exception $e) {
            $this->error("❌ Sync failed: {$e->getMessage()}");
            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Sync vehicles for a specific company.
     */
    protected function syncSpecificCompany(string $companyUuid, ?string $credentialUuid, bool $force, bool $includeInactive, bool $dryRun): int
    {
        $company = Company::where('uuid', $companyUuid)->first();

        if (!$company) {
            $this->error("❌ Company not found: {$companyUuid}");

            return Command::FAILURE;
        }

        $this->info("🏢 Syncing company: {$company->name} ({$companyUuid})");

        $credentialsQuery = SamsaraCredential::where('company_uuid', $companyUuid);

        if ($credentialUuid) {
            $credentialsQuery->where('uuid', $credentialUuid);
        } else {
            $credentialsQuery->where('is_active', true);
        }

        $credentials = $credentialsQuery->get();

        if ($credentials->isEmpty()) {
            $this->warn("⚠️  No active Samsara credentials found for company: {$company->name}");

            return Command::SUCCESS;
        }

        $totalSynced = 0;
        $totalErrors = 0;

        foreach ($credentials as $credential) {
            $this->info("🔑 Using credential: {$credential->name}");

            if ($dryRun) {
                $result = $this->syncService->previewSync($credential, $includeInactive);
                $this->displayDryRunResults($result);
            } else {
                $result = $this->syncService->syncVehicles($credential, $force, $includeInactive);
                $this->displaySyncResults($result);

                $totalSynced += $result['synced'] ?? 0;
                $totalErrors += $result['errors'] ?? 0;
            }
        }

        if (!$dryRun) {
            $this->info("✅ Company sync completed: {$totalSynced} vehicles synced, {$totalErrors} errors");
        }

        return Command::SUCCESS;
    }

    /**
     * Sync vehicles for all companies with Samsara credentials.
     */
    protected function syncAllCompanies(bool $force, bool $includeInactive, bool $dryRun): int
    {
        $this->info('🌍 Syncing all companies with Samsara credentials...');

        $companies = Company::whereHas('samsaraCredentials', function ($query) {
            $query->where('is_active', true);
        })->get();

        if ($companies->isEmpty()) {
            $this->warn('⚠️  No companies found with active Samsara credentials');

            return Command::SUCCESS;
        }

        $this->info("📊 Found {$companies->count()} companies to sync");

        $progressBar = $this->output->createProgressBar($companies->count());
        $progressBar->start();

        $totalSynced        = 0;
        $totalErrors        = 0;
        $companiesProcessed = 0;

        foreach ($companies as $company) {
            $progressBar->setMessage("Syncing: {$company->name}");

            try {
                $credentials = $company->samsaraCredentials()->where('is_active', true)->get();

                foreach ($credentials as $credential) {
                    if ($dryRun) {
                        $result = $this->syncService->previewSync($credential, $includeInactive);
                    } else {
                        $result = $this->syncService->syncVehicles($credential, $force, $includeInactive);
                        $totalSynced += $result['synced'] ?? 0;
                        $totalErrors += $result['errors'] ?? 0;
                    }
                }

                $companiesProcessed++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("❌ Error syncing company {$company->name}: {$e->getMessage()}");
                $totalErrors++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("🔍 Dry run completed for {$companiesProcessed} companies");
        } else {
            $this->info("✅ Global sync completed: {$companiesProcessed} companies processed, {$totalSynced} vehicles synced, {$totalErrors} errors");
        }

        return Command::SUCCESS;
    }

    /**
     * Display sync results.
     */
    protected function displaySyncResults(array $result): void
    {
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Vehicles', $result['total'] ?? 0],
                ['Synced Successfully', $result['synced'] ?? 0],
                ['Created New', $result['created'] ?? 0],
                ['Updated Existing', $result['updated'] ?? 0],
                ['Linked to FleetOps', $result['linked'] ?? 0],
                ['Errors', $result['errors'] ?? 0],
                ['Duration (seconds)', round($result['duration'] ?? 0, 2)],
            ]
        );

        if (!empty($result['error_details'])) {
            $this->warn('⚠️  Errors encountered:');
            foreach ($result['error_details'] as $error) {
                $this->line("  • {$error}");
            }
        }
    }

    /**
     * Display dry run results.
     */
    protected function displayDryRunResults(array $result): void
    {
        $this->info('🔍 Dry Run Results:');
        $this->table(
            ['Action', 'Count'],
            [
                ['Vehicles to Sync', $result['total_vehicles'] ?? 0],
                ['New Vehicles to Create', $result['new_vehicles'] ?? 0],
                ['Existing Vehicles to Update', $result['existing_vehicles'] ?? 0],
                ['FleetOps Vehicles to Link', $result['linkable_vehicles'] ?? 0],
            ]
        );

        if (!empty($result['sample_vehicles'])) {
            $this->info('📋 Sample vehicles to be synced:');
            foreach (array_slice($result['sample_vehicles'], 0, 5) as $vehicle) {
                $this->line("  • {$vehicle['name']} ({$vehicle['id']})");
            }
            if (count($result['sample_vehicles']) > 5) {
                $this->line('  ... and ' . (count($result['sample_vehicles']) - 5) . ' more');
            }
        }
    }
}
