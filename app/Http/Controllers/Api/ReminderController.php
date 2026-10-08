<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DiscordReminder;
use Illuminate\Http\JsonResponse;

class ReminderController extends Controller
{
    public function send(DiscordReminder $reminder): JsonResponse
    {
        $result = $reminder->send();

        return response()->json([
            'success' => $result['sent'],
            'message' => $result['message'],
            'data' => ['unpaid_count' => $result['unpaid_count']],
        ], $result['sent'] ? 200 : 422);
    }
}
