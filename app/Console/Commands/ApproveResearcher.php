<?php

namespace App\Console\Commands;
use App\Models\Researcher;
use App\Models\User;

use Illuminate\Console\Command;

class ApproveResearcher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    protected $signature = 'researcher:approve {id} {--admin=1}';

    /**
     * Execute the console command.
     */
    public function handle()
{
    $researcher = Researcher::findOrFail($this->argument('id'));
    $admin = User::find($this->option('admin'));

    $researcher->approve($admin);

    $this->info("Researcher {$researcher->full_name} approved!");
}
}
