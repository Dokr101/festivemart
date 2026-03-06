<?php
// ── Khalti Payment Gateway Configuration ─────────────────────────────────────
// FestiVmart — Live Credentials

define('KHALTI_SECRET_KEY', 'faf4aa799dca4acdbbca70ade749320e');
define('KHALTI_PUBLIC_KEY', '22306c7c1e894491aed13f5b327ca7a4');

// Khalti v2 ePay API Endpoints (Test/Sandbox — use a.khalti.com for test keys)
define('KHALTI_INITIATE_URL', 'https://a.khalti.com/api/v2/epayment/initiate/');
define('KHALTI_LOOKUP_URL', 'https://a.khalti.com/api/v2/epayment/lookup/');

// Callback URL — Khalti will redirect here after payment
define('KHALTI_RETURN_URL', 'http://localhost/festivemart/Payment/verify.php');
define('KHALTI_WEBSITE_URL', 'http://localhost/festivemart');
