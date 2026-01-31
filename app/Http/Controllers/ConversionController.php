<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

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

        // Prepare base log data
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

            // 4️⃣ Map data by LABEL (human-readable labels)
            $labeledData = $this->mapByLabel($item, $fields);

            // 5️⃣ Extract required fields
            // Note: pass both labeledData and raw $item (TITLE fallback)
            $name  = $this->extractName($labeledData, $item);
            $phone = $this->extractPhone($labeledData, $item);
            $email = $this->extractEmail($labeledData, $item);
            $city  = $this->extractCity($labeledData, $item);

            // 6️⃣ Validate required fields: first + last + phone + email are required
            $missing = [];
            if (empty($name['first_name'])) $missing[] = 'first_name';
            if (empty($name['last_name']))  $missing[] = 'last_name';
            if (empty($phone))              $missing[] = 'phone';
            if (empty($email))              $missing[] = 'email';

            if (!empty($missing)) {
                $logData['status'] = 'failed';
                $logData['error_message'] = 'Missing required fields: ' . implode(', ', $missing);
                WebhookLog::create($logData);
                return response()->json(['status' => 'failed', 'missing' => $missing], 422);
            }

            // 7️⃣ Prepare user_data with SHA256 normalized hashes for Meta
            Log::info('Conversion raw data:', [
                'first_name' => $name['first_name'],
                'last_name'  => $name['last_name'],
                'phone'      => $phone,
                'email'      => $email,
                'city'       => $city ?? 'N/A',
            ]);

            $userData = [
                'fn' => $this->hashForFB($name['first_name']),
                'ln' => $this->hashForFB($name['last_name']),
                'ph' => $this->hashForFB($phone),
                'em' => $this->hashForFB($email),
            ];

            $city = trim((string)($city ?? ''));
            if ($city !== '') {
                $userData['ct'] = $this->hashForFB($city);
            }

            // 8️⃣ Determine event type (case-sensitive)
            $eventName = $request->query('event_name', 'QUALIFIED');

            $customData = [
                'lead_id' => (string) $entityId,
            ];

            // 9️⃣ Handle PURCHASE events (DEAL)
            if ($entityType === 'DEAL' && $eventName === 'Purchase') {
                $customData['value']        = isset($labeledData['OPPORTUNITY']) ? (float) $labeledData['OPPORTUNITY'] : 0;
                $customData['currency']     = $labeledData['CURRENCY_ID'] ?? 'USD';
                $customData['content_type'] = 'product';
                $customData['content_name'] = $labeledData['TITLE'] ?? null;
            }

            // 1️⃣0️⃣ Build FB payload
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

            // 1️⃣1️⃣ Send to Facebook
            $response = Http::timeout(15)->post(
                "https://graph.facebook.com/v18.0/{$company->fb_pixel_id}/events",
                array_merge($fbPayload, ['access_token' => $company->fb_access_token])
            );

            $logData['fb_response'] = $response->json();
            $logData['status']      = $response->successful() ? 'success' : 'failed';
            if (!$response->successful()) {
                $logData['error_message'] = 'FB responded with HTTP ' . $response->status();
            }

        } catch (\Throwable $e) {
            $logData['status']        = 'failed';
            $logData['error_message'] = $e->getMessage();
            Log::error('ConversionController exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }

        // 1️⃣2️⃣ Save log
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

    /**
     * Map by human-readable label (formLabel | listLabel | title) -> value
     * Keeps label keys exactly as provided by field metadata (so we match by label).
     */
    private function mapByLabel(array $item, array $fields): array
    {
        $mapped = [];
        foreach ($item as $code => $value) {
            if (!isset($fields[$code])) {
                // If fields metadata is missing for this code, use the code as a fallback label
                $label = $code;
            } else {
                $label = $fields[$code]['formLabel'] ?? $fields[$code]['listLabel'] ?? $fields[$code]['title'] ?? $code;
            }
            // keep label as-is (human label). Trim it
            $label = trim((string)$label);
            $mapped[$label] = $value;
        }
        return $mapped;
    }

    /**
     * Extract phone by matching label substrings like 'phone', 'tel', 'mobile'
     * Returns the first phone found (string) or null.
     */
    private function extractPhone(array $labeledData, array $rawItem): ?string
    {
        // 1. Try standard Bitrix field 'PHONE'
        if (!empty($rawItem['PHONE']) && is_array($rawItem['PHONE'])) {
            foreach ($rawItem['PHONE'] as $p) {
                if (!empty($p['VALUE'])) return (string)$p['VALUE'];
            }
        }

        // 2. Try labeled data
        foreach ($labeledData as $label => $value) {
            $l = strtolower(trim($label));
            if (Str::contains($l, ['phone', 'tel', 'mobile', 'cell'])) {
                if ($value === 'Y' || $value === 'N') continue; // skip flags
                if (is_array($value)) {
                    if (isset($value[0]['VALUE'])) return (string)$value[0]['VALUE'];
                    return (string)($value[0] ?? null);
                }
                return (string)$value;
            }
        }
        return null;
    }

    /**
     * Extract email by matching label substrings like 'email', 'e-mail', 'mail'
     */
    private function extractEmail(array $labeledData, array $rawItem): ?string
    {
        // 1. Try standard Bitrix field 'EMAIL'
        if (!empty($rawItem['EMAIL']) && is_array($rawItem['EMAIL'])) {
            foreach ($rawItem['EMAIL'] as $e) {
                if (!empty($e['VALUE'])) return (string)$e['VALUE'];
            }
        }

        // 2. Try labeled data
        foreach ($labeledData as $label => $value) {
            $l = strtolower(trim($label));
            if (Str::contains($l, ['email', 'e-mail', 'mail'])) {
                if ($value === 'Y' || $value === 'N') continue; // skip flags
                if (is_array($value)) {
                    if (isset($value[0]['VALUE'])) return (string)$value[0]['VALUE'];
                    return (string)($value[0] ?? null);
                }
                return (string)$value;
            }
        }
        return null;
    }

    /**
     * Extract city if a label contains 'city'
     * Note: User asked label-based matching (not field codes)
     */
    private function extractCity(array $labeledData, array $rawItem): ?string
{
    // 1️⃣ Highest priority: standard Bitrix field
    if (!empty($rawItem['ADDRESS_CITY']) && is_string($rawItem['ADDRESS_CITY'])) {
        return trim($rawItem['ADDRESS_CITY']);
    }

    // 2️⃣ Search by human-readable labels (City / Town)
    foreach ($labeledData as $label => $value) {
        $labelNormalized = strtolower(trim((string) $label));

        if (!Str::contains($labelNormalized, ['city', 'town'])) {
            continue;
        }

        // Skip boolean flags
        if ($value === 'Y' || $value === 'N' || $value === null) {
            continue;
        }

        /*
         |-----------------------------------------
         | Value handling (ALL Bitrix cases)
         |-----------------------------------------
         */

        // Case 1: simple string
        if (is_string($value)) {
            $v = trim($value);
            if ($v !== '') {
                return $v;
            }
        }

        // Case 2: array (custom fields, multifields, isMultiple)
        if (is_array($value)) {

            // Example: ["Abu Dhabi"]
            if (isset($value[0]) && is_string($value[0])) {
                $v = trim($value[0]);
                if ($v !== '') {
                    return $v;
                }
            }

            // Example: [{ VALUE: "Abu Dhabi" }]
            if (isset($value[0]['VALUE']) && is_string($value[0]['VALUE'])) {
                $v = trim($value[0]['VALUE']);
                if ($v !== '') {
                    return $v;
                }
            }

            // Example: associative array
            foreach ($value as $v) {
                if (is_string($v)) {
                    $vv = trim($v);
                    if ($vv !== '') {
                        return $vv;
                    }
                }

                if (is_array($v) && isset($v['VALUE']) && is_string($v['VALUE'])) {
                    $vv = trim($v['VALUE']);
                    if ($vv !== '') {
                        return $vv;
                    }
                }
            }
        }
    }

    return null;
}


    /**
     * Extract first and last name by label heuristics; fallback to TITLE (raw $item)
     * Returns ['first_name' => ..., 'last_name' => ...]
     */
    private function extractName(array $labeledData, array $rawItem): array
    {
        // 1. Try standard Bitrix fields NAME and LAST_NAME first
        $first = $rawItem['NAME'] ?? null;
        $last  = $rawItem['LAST_NAME'] ?? null;

        // If LAST_NAME is missing, try to split NAME (case where full name is in NAME field)
        if (empty($last) && !empty($first)) {
            $parts = preg_split('/\s+/', trim((string)$first), 2);
            if (count($parts) > 1) {
                $first = $parts[0];
                $last  = $parts[1];
            }
        }

        // 2. If still missing, try labeled data
        if (empty($first) || empty($last)) {
            foreach ($labeledData as $label => $value) {
                $l = strtolower(trim($label));

                // Avoid matching dates or meta fields that might contain "last" or "name"
                if (Str::contains($l, ['date', 'time', 'status', 'id', 'has', 'source', 'type'])) continue;

                $val = is_array($value) ? ($value[0]['VALUE'] ?? null) : $value;
                if (!$val || $val === 'Y' || $val === 'N' || preg_match('/\d{4}-\d{2}-\d{2}/', (string)$val)) {
                    continue;
                }

                // Explicit first name labels
                if (empty($first) && Str::contains($l, ['first name', 'firstname', 'given name'])) {
                    $first = trim((string)$val);
                }
                // Explicit last name labels
                if (empty($last) && (Str::contains($l, ['last name', 'lastname', 'surname']) || $l === 'last')) {
                    $last = trim((string)$val);
                }
                // Generic name label
                if ((empty($first) || empty($last)) && ($l === 'name' || $l === 'full name' || $l === 'fullname')) {
                    $parts = preg_split('/\s+/', trim((string)$val), 2);
                    if (empty($first)) $first = $parts[0] ?? null;
                    if (empty($last))  $last  = $parts[1] ?? null;
                }
            }
        }

        // 3. Fallback to TITLE split
        if ((empty($first) || empty($last)) && !empty($rawItem['TITLE'])) {
            $title = trim((string)$rawItem['TITLE']);
            $titleClean = preg_replace('/\s*-\s*.*$/', '', $title);
            $parts = preg_split('/\s+/', $titleClean, 2);
            if (empty($first)) $first = $parts[0] ?? null;
            if (empty($last))  $last  = $parts[1] ?? null;
        }

        return [
            'first_name' => $first ? trim((string)$first) : null,
            'last_name'  => $last ? trim((string)$last) : null
        ];
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
