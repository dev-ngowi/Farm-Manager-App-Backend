<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SendVaccineReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-vaccine-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    // app/Console/Commands/SendVaccineReminders.php
public function handle()
{
    VaccinationSchedule::today()
        ->where('reminder_sent', false)
        ->with('animal.farmer')
        ->each(function ($schedule) {
            // Send SMS via Africa's Talking, Twilio, etc.
            // Sms::to($schedule->farmer->phone_number)
            //     ->message($schedule->vaccine_message)
            //     ->send();

            $schedule->update(['reminder_sent' => true]);
            $this->info("Reminder sent for animal {$schedule->animal->tag}");
        });
}
}
