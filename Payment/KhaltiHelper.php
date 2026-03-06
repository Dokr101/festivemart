<?php
require_once __DIR__ . '/KhaltiConfig.php';

/**
 * Initiate a Khalti ePay payment.
 *
 * @param  int    $amount_paisa   Amount in paisa (NPR × 100)
 * @param  string $order_id       Unique purchase order ID
 * @param  string $order_name     Short description shown on Khalti
 * @param  array  $customer       ['name', 'email', 'phone']
 * @return array  ['success'=>bool, 'payment_url'=>string, 'pidx'=>string, 'error'=>string]
 */
function khaltiInitiate(int $amount_paisa, string $order_id, string $order_name, array $customer): array
{
    $payload = [
        'return_url' => KHALTI_RETURN_URL,
        'website_url' => KHALTI_WEBSITE_URL,
        'amount' => $amount_paisa,
        'purchase_order_id' => $order_id,
        'purchase_order_name' => $order_name,
        'customer_info' => [
            'name' => $customer['name'] ?? 'Customer',
            'email' => $customer['email'] ?? '',
            'phone' => $customer['phone'] ?? '',
        ],
    ];

    $ch = curl_init(KHALTI_INITIATE_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Key ' . KHALTI_SECRET_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => false, // for localhost dev
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'error' => 'cURL error: ' . $curlErr];
    }

    $data = json_decode($response, true);

    if ($httpCode === 200 && isset($data['payment_url'], $data['pidx'])) {
        return [
            'success' => true,
            'payment_url' => $data['payment_url'],
            'pidx' => $data['pidx'],
        ];
    }

    $errorMsg = $data['detail'] ?? $data['error_key'] ?? 'Unknown Khalti error';
    return ['success' => false, 'error' => $errorMsg, 'raw' => $data];
}

/**
 * Verify/lookup a Khalti payment by pidx.
 *
 * @param  string $pidx  The pidx returned during initiation
 * @return array  ['success'=>bool, 'status'=>string, 'transaction_id'=>string, 'amount'=>int, ...]
 */
function khaltiVerify(string $pidx): array
{
    $ch = curl_init(KHALTI_LOOKUP_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['pidx' => $pidx]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Key ' . KHALTI_SECRET_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'error' => 'cURL error: ' . $curlErr];
    }

    $data = json_decode($response, true);

    if ($httpCode === 200 && isset($data['status'])) {
        return [
            'success' => true,
            'status' => $data['status'],          // Completed | Pending | Initiated | Refunded | Expired | User canceled | Failed
            'transaction_id' => $data['transaction_id'] ?? '',
            'amount' => $data['total_amount'] ?? 0,
            'fee' => $data['fee'] ?? 0,
            'raw' => $data,
        ];
    }

    $errorMsg = $data['detail'] ?? 'Verification failed';
    return ['success' => false, 'error' => $errorMsg, 'raw' => $data];
}
