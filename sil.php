<?php
include 'db.php';
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sorgu = $baglanti->prepare("DELETE FROM randevular WHERE id = ?");
    $sorgu->execute([$id]);
}
header("Location: admin.php");
?>