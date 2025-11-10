<?php

namespace App\Console\Commands;

use App\Models\ResearchDataExport;

use Illuminate\Console\Command;

class CleanupExpiredExports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-expired-exports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
{
    ResearchDataExport::where('expires_at', '<', now())
        ->where('download_count', 0)
        ->each(function ($export) {
            $export->delete();
            $this->info("Deleted expired export #{$export->export_id}");
        });
}
}
