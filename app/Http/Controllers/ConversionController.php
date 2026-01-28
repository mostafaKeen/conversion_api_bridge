<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ConversionController extends Controller
{
  public function handle(Request $request, string $token)
{
    // 1️⃣ Resolve Company
    $company = Company::where('outbound_token', $token)
        ->where('is_active', true)
        ->first();

    if (!$company) {
        return response()->json(['error' => 'Invalid token'], 404);
    }

    // Prepare log data
    $logData = [
        'company_id'      => $company->id,
        'bitrix_request'  => [
            'headers' => $request->headers->all(),
            'body'    => $request->all(),
        ],
        'fb_payload'      => null,
        'fb_response'     => null,
        'status'          => 'failed',
        'error_message'   => null,
    ];

    try {
        // 2️⃣ Extract entity
        $doc = data_get($request->all(), 'document_id.2');
        if (!$doc || !Str::startsWith($doc, ['LEAD_', 'DEAL_'])) {
            $logData['status'] = 'failed';
            $logData['error_message'] = 'Invalid entity ID';
            WebhookLog::create($logData);
            return response()->json(['status' => 'ignored']);
        }

        $entityType = Str::startsWith($doc, 'DEAL_') ? 'DEAL' : 'LEAD';
        $entityId   = (int) Str::replace(['LEAD_', 'DEAL_'], '', $doc);

        // 3️⃣ Fetch entity + fields
        $item = $this->bitrixCall(
            $company,
            $entityType === 'LEAD' ? 'crm.lead.get' : 'crm.deal.get',
            ['id' => $entityId]
        )['result'] ?? null;

        $fields = $this->bitrixCall(
            $company,
            $entityType === 'LEAD' ? 'crm.lead.fields' : 'crm.deal.fields'
        )['result'] ?? [];

        if (!$item || !$fields) {
            $logData['status'] = 'failed';
            $logData['error_message'] = 'Failed to fetch entity or fields';
            WebhookLog::create($logData);
            return response()->json(['status' => 'failed']);
        }

        // 4️⃣ Map data by LABEL
        $labeledData = $this->mapByLabel($item, $fields);

        // 5️⃣ Extract user data
        $name  = $this->extractName($labeledData);
        $phone = $this->extractPhone($labeledData);
        $email = $this->extractEmail($labeledData);
        $city  = $this->extractCity($labeledData);

        $userData = array_filter([
            'fn' => $name['first_name'] ? $this->hashForFB($name['first_name']) : null,
            'ln' => $name['last_name']  ? $this->hashForFB($name['last_name'])  : null,
            'ph' => $phone ? $this->hashForFB($phone) : null,
            'em' => $email ? $this->hashForFB($email) : null,
            'ct' => $city ? $this->hashForFB($city) : null,
        ]);

        // 6️⃣ Determine event type (case-sensitive)
        $eventName = $request->query('event_name', 'QUALIFIED');

        $customData = [
            'lead_id' => (string) $entityId,
        ];

        // 7️⃣ Handle PURCHASE events (DEAL)
        if ($entityType === 'DEAL' && $eventName === 'Purchase') {
            $customData['value']        = isset($labeledData['OPPORTUNITY']) ? (float) $labeledData['OPPORTUNITY'] : 0;
            $customData['currency']     = $labeledData['CURRENCY_ID'] ?? 'USD';
            $customData['content_type'] = 'product';
            $customData['content_name'] = $labeledData['TITLE'] ?? null;
        }

        // 8️⃣ Build FB payload
        $fbPayload = [
            'data' => [[
                'event_name'    => $eventName,
                'event_time'    => now()->timestamp,
                'action_source' => 'system_generated',
                'user_data'     => $userData,
                'custom_data'   => $customData,
            ]],
        ];

        $logData['fb_payload'] = $fbPayload;

        // 9️⃣ Send to Facebook
        $response = Http::timeout(15)->post(
            "https://graph.facebook.com/v18.0/{$company->fb_pixel_id}/events",
            array_merge($fbPayload, ['access_token' => $company->fb_access_token])
        );

        $logData['fb_response'] = $response->json();
        $logData['status']      = $response->successful() ? 'success' : 'failed';

    } catch (\Throwable $e) {
        $logData['status']        = 'failed';
        $logData['error_message'] = $e->getMessage();
    }

    // 1️⃣0️⃣ Save log
    WebhookLog::create($logData);

    return response()->json([
        'status'    => 'ok',
        'fb_status' => $response->status() ?? null,
    ]);
}



    // ======================================================
    // Helpers
    // ======================================================

    private function bitrixCall(Company $company, string $method, array $payload = [])
    {
        return Http::timeout(15)
            ->post($company->bitrix_webhook_url . $method . '.json', $payload)
            ->json();
    }

    private function mapByLabel(array $item, array $fields): array
    {
        $mapped = [];
        foreach ($item as $code => $value) {
            if (!isset($fields[$code])) continue;
            $label = $fields[$code]['formLabel'] ?? $fields[$code]['listLabel'] ?? $fields[$code]['title'] ?? $code;
            $mapped[$label] = $value;
        }
        return $mapped;
    }

    private function extractPhone(array $data): ?string
    {
        foreach ($data as $label => $value) {
            if (strtolower($label) === 'phone' && is_array($value)) {
                return $value[0]['VALUE'] ?? null;
            }
        }
        return null;
    }

    private function extractEmail(array $data): ?string
    {
        foreach ($data as $label => $value) {
            if (in_array(strtolower($label), ['e-mail', 'email'], true) && is_array($value)) {
                return $value[0]['VALUE'] ?? null;
            }
        }
        return null;
    }

    private function extractCity(array $data): ?string
    {
        foreach ($data as $label => $value) {
            if (strtolower($label) === 'city') {
                return is_array($value) ? null : (string) $value;
            }
        }
        return null;
    }

    private function extractName(array $data): array
    {
        $first = null;
        $last  = null;
        foreach ($data as $label => $value) {
            if (strtolower($label) === 'name') $first = trim((string) $value);
            if (strtolower($label) === 'last name') $last = trim((string) $value);
        }
        if ($first && !$last) {
            $parts = preg_split('/\s+/', $first, 2);
            $first = $parts[0] ?? $first;
            $last  = $parts[1] ?? null;
        }
        return ['first_name' => $first, 'last_name' => $last];
    }

    // ======================================================
    // SHA256 Hash for FB
    // ======================================================
    private function hashForFB(string $value): string
    {
        $v = trim(strtolower($value));
        $v = preg_replace('/\s+/', '', $v); // remove spaces
        return hash('sha256', $v);
    }
}
