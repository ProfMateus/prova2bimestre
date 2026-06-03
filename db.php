<?php
// Ele tenta ler do servidor online. Se não existir (você testando no PC local), usa o 'localhost'
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'sistema_avaliacao';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: ''; 
$port = getenv('DB_PORT') ?: '3306';

$dsn = "mysql:host=$host;dbname=$db;port=$port;charset=utf8mb4";
// ... resto do código de conexão PDO idêntico ao primeiro
