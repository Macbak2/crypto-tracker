import React from 'react';
import './DashboardPage.css';

function DashboardPage() {
 return (
  <div className="dashboard-page">
   <h2>Dashboard - Crypto Tracker</h2>

   <div className="welcome-section">
    <div className="welcome-card">
     <h3>👋 Witaj w systemie zarządzania operacjami kryptowalutowymi!</h3>
     <p>
      System pozwala na import i analizę operacji z giełdy Zonda (BitBay).
     </p>
    </div>

    <div className="features-grid">
     <div className="feature-card">
      <div className="feature-icon">📊</div>
      <h4>Import danych</h4>
      <p>Automatyczne wykrywanie typu pliku CSV i import do bazy danych</p>
     </div>

     <div className="feature-card">
      <div className="feature-icon">💹</div>
      <h4>Historia transakcji</h4>
      <p>Przeglądaj i filtruj wszystkie transakcje kupna i sprzedaży</p>
     </div>

     <div className="feature-card">
      <div className="feature-icon">📈</div>
      <h4>Analiza zysków</h4>
      <p>Obliczanie zysków i strat zgodnie z metodą FIFO</p>
     </div>

     <div className="feature-card">
      <div className="feature-icon">💰</div>
      <h4>Rozliczenie podatkowe</h4>
      <p>Przygotowanie danych do rozliczenia z urzędem skarbowym</p>
     </div>
    </div>

    <div className="quick-start">
     <h3>🚀 Szybki start:</h3>
     <ol>
      <li>Przejdź do zakładki <strong>Import</strong></li>
      <li>Wrzuć pliki CSV pobrane z giełdy Zonda</li>
      <li>System automatycznie rozpozna typ każdego pliku</li>
      <li>Kliknij "Importuj" i gotowe!</li>
     </ol>
    </div>

    <div className="info-box">
     <h4>ℹ️ Obsługiwane pliki:</h4>
     <ul>
      <li><strong>Transakcje</strong> - podstawowe dane o transakcjach handlowych</li>
      <li><strong>Szczegółowy raport</strong> - pełne informacje o operacjach</li>
      <li><strong>Operacje</strong> - szczegóły wszystkich operacji (opcjonalne)</li>
      <li><strong>Transfery</strong> - wpłaty i wypłaty (opcjonalne)</li>
     </ul>
    </div>
   </div>
  </div>
 );
}

export default DashboardPage;