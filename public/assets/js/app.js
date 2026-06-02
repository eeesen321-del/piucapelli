$(document).ready(function() {
    
    // Dashboard istatistiklerini çekme fonksiyonu
    function fetchDashboardStats() {
        // Eğer dashboard sayfasında değilsek AJAX isteği atma
        if ($('#stat-randevular').length === 0) return;

        $.ajax({
            url: '/admin/dashboard/stats', // Router yapınıza göre bu URL'i ayarlayın (getStats metoduna gitmeli)
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                $('#stat-randevular').text(data.bugunku_randevular);
                $('#stat-bekleyen').text(data.bekleyen_musteriler);
                $('#stat-gelir').text(data.bugunku_gelir + ' TL');
                $('#stat-personel').text(data.en_yogun_personel);
            },
            error: function() {
                console.error("Dashboard verileri güncellenemedi.");
            }
        });
    }

    // Sayfa yüklendiğinde verileri getir
    fetchDashboardStats();

    // 30 saniyede bir verileri güncelle
    setInterval(fetchDashboardStats, 30000);
});