<?php
// --- TEK MERKEZLİ İŞLEM MANTIĞI ---

// 1. KAYDETME (Ekleme ve Güncelleme Tek Blokta)
if (isset($_POST['oda_kaydet'])) {
    $adi = trim($_POST['oda_adi']);
    $aciklama = trim($_POST['aciklama']);
    $id = $_POST['oda_id'] ?? null; // ID varsa güncelleme, yoksa ekleme

    if ($adi) {
        if ($id > 0) {
            // Güncelleme
            $baglanti->prepare("UPDATE odalar SET oda_adi = ?, aciklama = ? WHERE id = ?")->execute([$adi, $aciklama, $id]);
        } else {
            // Ekleme
            $baglanti->prepare("INSERT INTO odalar (oda_adi, aciklama) VALUES (?, ?)")->execute([$adi, $aciklama]);
        }
    }
    header("Location: admin.php?tab=odalar"); 
    exit;
}

// 2. SİLME
if (isset($_GET['oda_sil_id'])) {
    $baglanti->prepare("DELETE FROM odalar WHERE id = ?")->execute([$_GET['oda_sil_id']]);
    header("Location: admin.php?tab=odalar"); 
    exit;
}

// --- VERİ ÇEKME ---
// Düzenlenecek veriyi çek
$duzenlenecek_oda = null;
if (isset($_GET['oda_duzenle'])) {
    $sorgu = $baglanti->prepare("SELECT * FROM odalar WHERE id = ?");
    $sorgu->execute([$_GET['oda_duzenle']]);
    $duzenlenecek_oda = $sorgu->fetch(PDO::FETCH_ASSOC);
}

// Listeyi çek
$odalar = $baglanti->query("SELECT * FROM odalar ORDER BY oda_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-4">
        <div class="card card-custom p-3">
            <h5><?= $duzenlenecek_oda ? 'Oda Düzenle' : 'Yeni Oda Ekle' ?></h5>
            <form method="POST">
                <?php if($duzenlenecek_oda): ?>
                    <input type="hidden" name="oda_id" value="<?= $duzenlenecek_oda['id'] ?>">
                <?php endif; ?>
                
                <div class="mb-3">
                    <label>Oda Adı</label>
                    <input type="text" name="oda_adi" class="form-control" required value="<?= htmlspecialchars($duzenlenecek_oda['oda_adi'] ?? '') ?>">
                </div>
                
                <div class="mb-3">
                    <label>Açıklama</label>
                    <input type="text" name="aciklama" class="form-control" value="<?= htmlspecialchars($duzenlenecek_oda['aciklama'] ?? '') ?>">
                </div>
                
                <button type="submit" name="oda_kaydet" class="btn btn-primary w-100">
                    <?= $duzenlenecek_oda ? 'Güncelle' : 'Kaydet' ?>
                </button>
                
                <?php if($duzenlenecek_oda): ?>
                    <a href="admin.php?tab=odalar" class="btn btn-secondary w-100 mt-2">Vazgeç / Yeni Ekle</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card card-custom">
            <div class="card-header-custom">
                <span class="card-title">Oda Listesi</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover m-0">
                    <thead>
                        <tr>
                            <th>Oda Adı</th>
                            <th>Açıklama</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($odalar as $oda): ?>
                        <tr>
                            <td><?= htmlspecialchars($oda['oda_adi']) ?></td>
                            <td><?= htmlspecialchars($oda['aciklama']) ?></td>
                            <td class="text-end">
                                <a href="?tab=odalar&oda_duzenle=<?= $oda['id'] ?>" class="btn btn-sm btn-info text-white"><i class="fa fa-edit"></i></a>
                                <a href="?tab=odalar&oda_sil_id=<?= $oda['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Silinsin mi?')"><i class="fa fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>