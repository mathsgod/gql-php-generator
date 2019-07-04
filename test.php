<?
require_once __DIR__ . "/vendor/autoload.php";

$pdo = new PDO("mysql:dbname=raymond;host=127.0.0.1;", "root", "111111", [
    PDO::ATTR_PERSISTENT => true,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
]);


$pdo = new GQL\Generator($pdo);

echo $pdo->output();
