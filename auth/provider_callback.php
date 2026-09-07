<?php

declare(strict_types=1);

require_once __DIR__ . '/provider.php';

try {
    provider_require_config([
        'MOPH_CLIENT_ID',
        'MOPH_CLIENT_SECRET',
        'MOPH_PROVIDER_CLIENT_ID',
        'MOPH_PROVIDER_SECRET_KEY',
        'MOPH_REDIRECT_URI',
    ]);

    $code = trim((string) ($_GET['code'] ?? ''));
    $state = (string) ($_GET['state'] ?? '');
    $savedState = (string) ($_SESSION['moph_oauth_state'] ?? '');
    $startedAt = (int) ($_SESSION['moph_oauth_started_at'] ?? 0);

    unset($_SESSION['moph_oauth_state'], $_SESSION['moph_oauth_started_at']);

    if ($code === '') {
        throw new RuntimeException('ไม่ได้รับ authorization code จาก MOPH Health ID');
    }

    if ($savedState === '' || $state === '' || !hash_equals($savedState, $state)) {
        throw new RuntimeException('OAuth state ไม่ถูกต้อง กรุณาลองเข้าสู่ระบบใหม่');
    }

    if ($startedAt > 0 && (time() - $startedAt) > 600) {
        throw new RuntimeException('คำขอเข้าสู่ระบบหมดอายุ กรุณาลองใหม่อีกครั้ง');
    }

    // 1) Exchange authorization code for Health ID access token.
    $tokenResponse = provider_http_json(
        'POST',
        provider_health_id_url() . '/api/v1/token',
        ['Content-Type: application/x-www-form-urlencoded'],
        null,
        [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => provider_redirect_uri(),
            'client_id' => provider_config('MOPH_CLIENT_ID'),
            'client_secret' => provider_config('MOPH_CLIENT_SECRET'),
        ]
    );

    if ($tokenResponse['http_code'] < 200 || $tokenResponse['http_code'] >= 300) {
        throw new RuntimeException('Health ID ไม่อนุญาตให้เข้าสู่ระบบ (HTTP ' . $tokenResponse['http_code'] . ')');
    }

    $healthData = $tokenResponse['data']['data'] ?? [];
    $healthAccessToken = (string) ($healthData['access_token'] ?? '');

    if ($healthAccessToken === '') {
        throw new RuntimeException('ไม่พบ Health ID access token จาก MOPH');
    }

    // 2) Exchange Health ID access token for Provider ID access token.
    $providerTokenResponse = provider_http_json(
        'POST',
        provider_api_url() . '/api/v1/services/token',
        ['Content-Type: application/json', 'Accept: application/json'],
        [
            'client_id' => provider_config('MOPH_PROVIDER_CLIENT_ID'),
            'secret_key' => provider_config('MOPH_PROVIDER_SECRET_KEY'),
            'token_by' => 'Health ID',
            'token' => $healthAccessToken,
        ]
    );

    if ($providerTokenResponse['http_code'] < 200 || $providerTokenResponse['http_code'] >= 300) {
        if ($providerTokenResponse['http_code'] === 400) {
            throw new RuntimeException('บัญชีนี้ยังไม่มี MOPH Provider ID หรือไม่สามารถใช้ Provider ID ได้');
        }

        throw new RuntimeException('Provider ID ไม่อนุญาตให้เข้าสู่ระบบ (HTTP ' . $providerTokenResponse['http_code'] . ')');
    }

    $providerData = $providerTokenResponse['data']['data'] ?? [];
    $providerAccessToken = (string) ($providerData['access_token'] ?? '');

    if ($providerAccessToken === '') {
        throw new RuntimeException('ไม่พบ Provider ID access token จาก MOPH');
    }

    // 3) Read the Provider profile.
    $profileResponse = provider_http_json(
        'GET',
        provider_api_url() . '/api/v1/services/profile',
        [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $providerAccessToken,
            'client-id: ' . provider_config('MOPH_PROVIDER_CLIENT_ID'),
            'secret-key: ' . provider_config('MOPH_PROVIDER_SECRET_KEY'),
        ]
    );

    if ($profileResponse['http_code'] < 200 || $profileResponse['http_code'] >= 300) {
        throw new RuntimeException('ไม่สามารถอ่านข้อมูล Provider ID ได้ (HTTP ' . $profileResponse['http_code'] . ')');
    }

    $profile = $profileResponse['data']['data'] ?? [];
    $providerId = trim((string) ($profile['provider_id'] ?? ''));

    if ($providerId === '') {
        throw new RuntimeException('MOPH ไม่ส่งเลข Provider ID กลับมา');
    }

    // Optional organization restriction. Leave empty to allow any valid Provider ID.
    $allowedHcode = trim((string) provider_config('MOPH_ALLOWED_HCODE', ''));
    if ($allowedHcode !== '') {
        $organizationMatched = false;

        foreach (($profile['organization'] ?? []) as $organization) {
            if ((string) ($organization['hcode'] ?? '') === $allowedHcode) {
                $organizationMatched = true;
                break;
            }
        }

        if (!$organizationMatched) {
            throw new RuntimeException('Provider ID นี้ไม่ได้สังกัดหน่วยงานที่อนุญาตให้เข้า BBH Dashboard');
        }
    }

    session_regenerate_id(true);

    $_SESSION['provider_auth'] = [
        'provider_id' => $providerId,
        'account_id' => (string) ($profile['account_id'] ?? $providerData['account_id'] ?? ''),
        'name_th' => (string) ($profile['name_th'] ?? ''),
        'firstname_th' => (string) ($profile['firstname_th'] ?? ''),
        'lastname_th' => (string) ($profile['lastname_th'] ?? ''),
        'organization' => $profile['organization'] ?? [],
        'login_at' => date('c'),
        'provider_token_expires_at' => (string) ($providerData['expiration_date'] ?? ''),
    ];

    $_SESSION['provider_login_success'] = 'เข้าสู่ระบบด้วย MOPH Provider ID สำเร็จ';

    header('Location: ' . BASE_URL . 'index.php?provider_login=success', true, 302);
    exit;
} catch (Throwable $e) {
    $_SESSION['provider_login_error'] = $e->getMessage();
    header('Location: ' . BASE_URL . 'index.php?provider_login=error', true, 302);
    exit;
}
