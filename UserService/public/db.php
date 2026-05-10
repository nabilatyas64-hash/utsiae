<?php
try {
    $pdo = new PDO('mysql:host=user-db;dbname=laundry_user_db', 'root', 'root');
    echo "Connected!";
} catch (Exception $e) {
    echo $e->getMessage();
}
