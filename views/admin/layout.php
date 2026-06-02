<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
</head>
<body>
    <aside class="sidebar">
        <div class="logo">İşletme Logosu</div>
        <nav class="menu">
            <a href="/admin/dashboard" class="menu-item">🏠 Gösterge Paneli</a>
            <a href="/admin/calendar" class="menu-item">📅 Takvim</a>
            
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="/admin/customers" class="menu-item">👥 Müşteriler</a>
                <a href="/admin/settings" class="menu-item">⚙️ Ayarlar</a>
            <?php endif; ?>
        </nav>
    </aside>

    <main class="content">
        <?= $content ?? '' ?>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="/public/assets/js/app.js"></script>
</body>
</html>