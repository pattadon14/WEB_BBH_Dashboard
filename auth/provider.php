<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function provider_config(string $key, ?string $default = null): ?string
{
    static $loaded = false;

    if (!$loaded) {
        $envFile = dirname(__DIR__) . '/.env';

        if (is_file($envFile)) {
            $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
            $dotenv->safeLoad();
        }

        $loaded = true;
    }

    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

function provider_require_config(array $keys): void
{
    foreach ($keys as $key) {
        if (!provider_config($key)) {
            http_response_code(500);
            exit('MOPH Provider ID ยังไม่ได้ตั้งค่า ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8'));
        }
    }
}

function provider_http_json(
    string $method,
    string $url,
    array $headers = [],
    ?array $jsonBody = null,
    ?array $formBody = null
): array {
    $ch = curl_init($url);

    if ($ch === false) {
        throw new RuntimeException('ไม่สามารถเริ่มการเชื่อมต่อกับ MOPH ได้');
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
    ];

    if ($jsonBody !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif ($formBody !== null) {
        $options[CURLOPT_POSTFIELDS] = http_build_query($formBody, '', '&', PHP_QUERY_RFC3986);
    }

    curl_setopt_array($ch, $options);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('เชื่อมต่อ MOPH ไม่สำเร็จ: ' . $curlError);
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('MOPH ส่งข้อมูลตอบกลับที่ไม่ใช่ JSON');
    }

    return [
        'http_code' => $httpCode,
        'data' => $decoded,
        'raw' => $raw,
    ];
}

function provider_health_id_url(): string
{
    return rtrim((string) provider_config('MOPH_HEALTH_ID_URL', 'https://uat-moph.id.th'), '/');
}

function provider_api_url(): string
{
    return rtrim((string) provider_config('MOPH_PROVIDER_URL', 'https://uat-provider.id.th'), '/');
}

function provider_redirect_uri(): string
{
    return (string) provider_config(
        'MOPH_REDIRECT_URI',
        rtrim((string) provider_config('APP_URL', ''), '/') . '/auth/provider_callback.php'
    );
}

function provider_is_logged_in(): bool
{
    return !empty($_SESSION['provider_auth']['provider_id']);
}

function provider_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }

    session_destroy();
}
