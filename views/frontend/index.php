<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Randevu Al</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; display: flex; justify-content: center; padding-top: 50px; }
        .form-container { background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        input, select { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #27ae60; color: #fff; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; }
        button:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Randevu Talebi Oluştur</h2>
        <form id="randevuForm">
            <input type="text" id="ad_soyad" placeholder="Adınız Soyadınız" required>
            <input type="text" id="telefon" placeholder="Telefon (Örn: 05551234567)" required>
            <select id="hizmet" required>
                <option value="Ağda">Ağda</option>
                <option value="Lazer">Lazer</option>
            </select>
            <input type="date" id="tarih" required>
            <input type="time" id="saat" required>
            <button type="submit">Gönder</button>
        </form>
        <div id="sonuc" style="margin-top: 15px; text-align: center; font-weight: bold;"></div>
    </div>

    <script>
        $('#randevuForm').submit(function(e) {
            e.preventDefault();
            $.ajax({
                url: '/randevu-al',
                type: 'POST',
                data: {
                    ad_soyad: $('#ad_soyad').val(),
                    telefon: $('#telefon').val(),
                    hizmet_adi: $('#hizmet').val(),
                    randevu_tarihi: $('#tarih').val(),
                    randevu_saati: $('#saat').val()
                },
                success: function(response) {
                    $('#sonuc').html('<span style="color:#27ae60;">Talebiniz alındı, sizi arayacağız.</span>');
                    $('#randevuForm')[0].reset();
                },
                error: function() {
                    $('#sonuc').html('<span style="color:#e74c3c;">Bir hata oluştu, lütfen tekrar deneyin.</span>');
                }
            });
        });
    </script>
</body>
</html>