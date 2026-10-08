<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\KasType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordReminder
{
    /** @return array{sent: bool, message: string, unpaid_count: int} */
    public function send(): array
    {
        $kasTypes = KasType::where('is_active', true)
            ->with(['bills' => fn ($q) => $q->unpaid()->with('user')])
            ->get();

        $lines = [];
        foreach ($kasTypes as $kas) {
            foreach ($kas->bills as $bill) {
                /** @var Bill $bill */
                if (! $bill->user || ! $bill->user->isVerified()) {
                    continue;
                }
                $lines[] = '- '.$bill->user->name.' ('.$bill->user->nim.') — '.$kas->name.' Rp '.number_format($bill->amount, 0, ',', '.');
            }
        }

        if ($lines === []) {
            return ['sent' => true, 'message' => 'Semua tagihan lunas. Tidak ada yang diingatkan.', 'unpaid_count' => 0];
        }

        $webhook = (string) config('services.discord.webhook_url', '');
        if ($webhook === '') {
            return ['sent' => false, 'message' => 'DISCORD_WEBHOOK_URL belum dikonfigurasi.', 'unpaid_count' => count($lines)];
        }

        $header = '**Smart-Kas**: '.count($lines).' tagihan belum lunas menjelang akhir bulan:';
        $chunks = $this->chunks($header, $lines);
        $failed = false;

        foreach ($chunks as $chunk) {
            try {
                $response = Http::timeout(10)->post($webhook, ['content' => $chunk]);
                if (! $response->successful()) {
                    Log::warning('Discord reminder ditolak: HTTP '.$response->status());
                    $failed = true;
                    break;
                }
            } catch (\Throwable $e) {
                Log::warning('Discord reminder gagal: '.$e->getMessage());
                $failed = true;
                break;
            }
        }

        if ($failed) {
            return ['sent' => false, 'message' => 'Gagal mengirim ke Discord, sudah dicatat di log.', 'unpaid_count' => count($lines)];
        }

        return ['sent' => true, 'message' => 'Pengingat terkirim ke Discord ('.count($lines).' tagihan).', 'unpaid_count' => count($lines)];
    }

    /** @param string[] $lines @return string[] */
    private function chunks(string $header, array $lines): array
    {
        $chunks = [];
        $current = $header;
        foreach ($lines as $line) {
            if (strlen($current) + strlen($line) + 1 > 2000) {
                $chunks[] = $current;
                $current = $header;
            }
            $current .= "\n".$line;
        }
        $chunks[] = $current;

        return $chunks;
    }
}
