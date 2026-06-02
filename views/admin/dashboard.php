<div class="dashboard-grid">
    <div class="card">
        <h3>🟢 Bugünkü Randevular</h3>
        <div class="value" id="stat-randevular">...</div>
    </div>
    <div class="card">
        <h3>🟡 Bekleyen Müşteriler</h3>
        <div class="value" id="stat-bekleyen">...</div>
    </div>
    <div class="card">
        <h3>💰 Bugünkü Gelir</h3>
        <div class="value" id="stat-gelir">...</div>
    </div>
    <div class="card">
        <h3>👩‍🎤 En Yoğun Personel</h3>
        <div class="value" id="stat-personel">...</div>
    </div>
</div>

<style>
.dashboard-grid { 
    display: grid; 
    grid-template-columns: repeat(2, 1fr); 
    gap: 20px; 
}
.card { 
    background: #fff; 
    padding: 30px; 
    border-radius: 10px; 
    box-shadow: 0 4px 6px rgba(0,0,0,0.05); 
    text-align: center; 
}
.card h3 { 
    margin: 0 0 15px 0; 
    font-size: 1.2em; 
    color: #555; 
}
.card .value { 
    font-size: 2em; 
    font-weight: bold; 
    color: #222; 
}
@media (max-width: 768px) {
    .dashboard-grid { grid-template-columns: 1fr; }
}
</style>