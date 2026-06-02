<?php
// functions.php

/**
 * Telefon numarasını +90 555 555 55 55 formatına çevirir.
 * Desteklenen giriş formatları: 05551234567, 5551234567, 905551234567, +905551234567
 */
function formatTel($tel) {
    if (empty($tel)) return '-';
    // Sadece rakamları al
    $sadece_rakam = preg_replace('/[^0-9]/', '', $tel);
    // Başındaki ülke kodunu temizle
    if (substr($sadece_rakam, 0, 2) === '90') {
        $sadece_rakam = substr($sadece_rakam, 2);
    }
    if (substr($sadece_rakam, 0, 1) === '0') {
        $sadece_rakam = substr($sadece_rakam, 1);
    }
    // 10 haneli Türk numarası
    if (strlen($sadece_rakam) === 10) {
        return '+90 ' 
            . substr($sadece_rakam, 0, 3) . ' ' 
            . substr($sadece_rakam, 3, 3) . ' ' 
            . substr($sadece_rakam, 6, 2) . ' ' 
            . substr($sadece_rakam, 8, 2);
    }
    // Format uymazsa orijinali döndür
    return $tel;
}

// functions.php dosyasındaki ilgili alanları doldur:
function mutluSmsGonder($telefon, $mesaj) {
    // 1. NUMARA TEMİZLEME (YENİ EKLENEN KISIM)
    // Boşlukları, parantezleri ve + işaretlerini siler, sadece rakamları bırakır.
    $telefon = preg_replace('/[^0-9]/', '', $telefon);
    
    // Eğer numara 0 ile başlıyorsa (Örn: 0532...), baştaki 0'ı atar.
    if (substr($telefon, 0, 1) == '0') {
        $telefon = substr($telefon, 1);
    }
    // Eğer numara 90 ile başlıyorsa (Örn: 90532...), baştaki 90'ı atar.
    if (substr($telefon, 0, 2) == '90') {
        $telefon = substr($telefon, 2);
    }
    $user   = "BURAYA_KULLANICI_ADI";  // Mutlucell kullanıcı adın
    $pass   = "BURAYA_SIFRE";          // Mutlucell şifren
    $title  = "BASLIGIN";              // Mutlucell'de onaylı SMS başlığın (Örn: PIUCAPELLI)

    $xmlString = '<?xml version="1.0" encoding="UTF-8"?>
    <mainbody>
        <header>
            <user>'. $user .'</user>
            <password>'. $pass .'</password>
            <stip>2</stip>
            <originator>'. $title .'</originator>
        </header>
        <body>
            <msg><![CDATA['. $mesaj .']]></msg>
            <no>'. $telefon .'</no>
        </body>
    </mainbody>';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.mutlusms.com/sendsms');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlString);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
    $result = curl_exec($ch);
    curl_close($ch);

    return $result; 
}
?>