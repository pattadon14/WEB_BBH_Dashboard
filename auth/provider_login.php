<?php

declare(strict_types=1);

require_once __DIR__ . '/provider.php';

provider_require_config([
    'MOPH_CLIENT_ID',
    'MOPH_REDIRECT_URI',
]);

$state = bin2hex(random_bytes(32));
$_SESSION['moph_oauth_state'] = $state;
$_SESSION['moph_oauth_started_at'] = time();

$params = [
    'client_id' => provider_config('MOPH_CLIENT_ID'),
    'redirect_uri' => provider_redirect_uri(),
    'response_type' => 'code',
    'state' => $state,
];

$loginUrl = provider_health_id_url() . '/oauth/redirect?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

header('Location: ' . $loginUrl, true, 302);
exit;
