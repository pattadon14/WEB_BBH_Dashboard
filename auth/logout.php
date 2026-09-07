<?php

declare(strict_types=1);

require_once __DIR__ . '/provider.php';

provider_logout();

header('Location: ' . BASE_URL, true, 302);
exit;
