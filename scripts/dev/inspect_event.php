<?php
require dirname(__DIR__, 2) . '/config/database.php';

$pdo = (new Database())->connect();

$eventId = 'MES-20260901-LINELAM1-000002-15E3';

$stmt = $pdo->prepare(
    "SELECT * FROM mes_production_events WHERE event_id = ?"
);

$stmt->execute([$eventId]);

print_r($stmt->fetch(PDO::FETCH_ASSOC));
