<?php

namespace App\Console\Commands;

use App\Services\DiscordReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:send')]
#[Description('Kirim pengingat tagihan belum bayar ke Discord')]
class SendPaymentReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DiscordReminder $reminder)
    {
        $result = $reminder->send();
        $this->info($result['message']);

        return $result['sent'] ? self::SUCCESS : self::FAILURE;
    }
}
