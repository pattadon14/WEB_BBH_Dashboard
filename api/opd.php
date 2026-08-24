<?php

require_once '../config/database.php';

header('Content-Type: application/json');

try {

    $sql = "SELECT COUNT
	( DISTINCT hn ) AS month_patient,
	COUNT ( DISTINCT vn ) AS month_visit,
	COUNT ( DISTINCT CASE WHEN vstdate = CURRENT_DATE THEN vn END ) AS today 
    FROM ovst 
    WHERE
	vstdate BETWEEN DATE_TRUNC( 'month', CURRENT_DATE ) AND CURRENT_DATE
	AND main_dep IN ('002','009','010','021','023','029','036','076','080','088','096','108','109',
					'110','111','115','121','125','126','127','128','129','130','131','134','144',
					'162','176','177','191','199','201','207','230','231','232','238','244',
					'204','035','039','056','057','059','060','087','090','093','097','099','118',
					'136','138','139','142','143','173','197','206','237','242','248','249');
    ";

    $stmt = $conn->query($sql);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($result);

} catch (PDOException $e) {

    echo json_encode([
        'error' => $e->getMessage()
    ]);

}