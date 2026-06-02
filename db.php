<?php
date_default_timezone_set('Europe/Istanbul');
$host = "localhost";
$db_adi = "piucapelli_db";
$kullanici = "root";
$sifre = "";

try {
    $baglanti = new PDO(
        "mysql:host=$host;dbname=$db_adi;charset=utf8",
        $kullanici,
        $sifre,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}