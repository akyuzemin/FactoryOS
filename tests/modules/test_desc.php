<?php
require dirname(__DIR__, 2) . '/config/database.php';
$pdo = (new Database())->connect();
print_r($pdo->query("DESCRIBE units")->fetchAll(PDO::FETCH_ASSOC));
