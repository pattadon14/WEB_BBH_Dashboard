<?php

header('Content-Type: application/json');

require_once '../config/database.php';

$sql = "
SELECT COUNT(*) as total
FROM an_stat
WHERE regdate = CURRENT_DATE
";

$stmt = $conn->query($sql);

$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($result);