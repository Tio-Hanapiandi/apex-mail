<?php

namespace App\Http\Controllers;

use App\Services\InboundMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InboundMailController extends Controller
{
    protected InboundMailService $inboundService;

    public function __construct(InboundMailService $inboundService)
    {
        $this->inboundService = $inboundService;
    }

    /**
     * Handle incoming webhook from Brevo Inbound Parsing or cloud routing.
     *
     * Endpoint: POST /api/mail/inbound
     * Optional Query: ?token=your-secret-token
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Optional security token check
        $expectedToken = config('services.inbound.webhook_token', env('INBOUND_WEBHOOK_TOKEN'));
        if (!empty($expectedToken)) {
            $receivedToken = $request->query('token') 
                ?? $request->header('X-Webhook-Token') 
                ?? $request->bearerToken();

            if ($receivedToken !== $expectedToken) {
                Log::warning('InboundMailController: Unauthorized webhook attempt with invalid token.');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Token webhook tidak valid atau tidak cocok.',
                ], 401);
            }
        }

        // 2. Process payload
        try {
            $result = $this->inboundService->processInbound($request);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['delivered_count'] > 0 
                    ? "Berhasil memproses dan memasukkan {$result['delivered_count']} email ke kotak masuk." 
                    : "Webhook diterima namun tidak ada akun lokal yang cocok.",
                'delivered_count' => $result['delivered_count'],
                'delivered_emails' => $result['delivered_emails'],
                'errors' => $result['errors'],
            ], 200);
        } catch (\Exception $e) {
            Log::error('InboundMailController Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses email masuk: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Simulator endpoint for testing inbound delivery from Admin UI or local curl.
     */
    public function simulate(Request $request)
    {
        $validated = $request->validate([
            'from_email' => 'required|email',
            'from_name' => 'nullable|string|max:100',
            'to_email' => 'required|email',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
            'body_text' => 'nullable|string',
        ]);

        $payload = [
            'items' => [
                [
                    'MessageId' => '<simulated-' . uniqid() . '@' . parse_url($validated['from_email'], PHP_URL_HOST) . '>',
                    'From' => [
                        'Name' => $validated['from_name'] ?: 'Simulasi Pengirim',
                        'Address' => $validated['from_email'],
                    ],
                    'To' => [
                        [
                            'Name' => '',
                            'Address' => $validated['to_email'],
                        ]
                    ],
                    'Subject' => $validated['subject'],
                    'RawHtmlBody' => $validated['body_html'],
                    'RawTextBody' => $validated['body_text'] ?: strip_tags($validated['body_html']),
                    'SentAtDate' => now()->toIso8601String(),
                ]
            ]
        ];

        $simulatedRequest = Request::create('/api/mail/inbound', 'POST', $payload);

        // Include uploaded test attachment if any
        if ($request->hasFile('attachments')) {
            $simulatedRequest->files->set('attachments', $request->file('attachments'));
        }

        $result = $this->inboundService->processInbound($simulatedRequest);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        if ($result['delivered_count'] > 0) {
            return redirect()->back()->with('success', "Simulasi sukses! Email masuk berhasil dikirimkan ke kotak masuk {$validated['to_email']}.");
        }

        return redirect()->back()->with('warning', "Simulasi selesai, namun alamat {$validated['to_email']} tidak ditemukan sebagai akun aktif di ApexMail.");
    }
}
