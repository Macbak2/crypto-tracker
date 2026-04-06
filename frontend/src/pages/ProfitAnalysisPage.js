import React, { useState, useEffect, useCallback } from 'react';
import { getProfitAnalysis } from '../services/api';
import './ProfitAnalysisPage.css';

const ProfitAnalysisPage = () => {
 const [analysis, setAnalysis] = useState(null);
 const [loading, setLoading] = useState(true);
 const [error, setError] = useState(null);
 const [selectedYear, setSelectedYear] = useState('all');
 const [expandedCrypto, setExpandedCrypto] = useState(null);

 const fetchAnalysis = useCallback(async () => {
  setLoading(true);
  setError(null);

  try {
   const data = await getProfitAnalysis(selectedYear);

   if (data.success) {
    setAnalysis(data);
   } else {
    setError(data.error || 'Błąd pobierania danych');
   }
  } catch (err) {
   setError('Błąd połączenia: ' + err.message);
  } finally {
   setLoading(false);
  }
 }, [selectedYear]);

 useEffect(() => {
  fetchAnalysis();
 }, [fetchAnalysis]);

 const formatCurrency = (value) => {
  return parseFloat(value).toLocaleString('pl-PL', {
   minimumFractionDigits: 2,
   maximumFractionDigits: 2
  }) + ' PLN';
 };

 const formatNumber = (num, decimals = 8) => {
  return parseFloat(num).toLocaleString('pl-PL', {
   minimumFractionDigits: 2,
   maximumFractionDigits: decimals
  });
 };

 const getAvailableYears = () => {
  const currentYear = new Date().getFullYear();
  const years = ['all'];
  for (let year = 2017; year <= currentYear; year++) {
   years.push(year);
  }
  return years;
 };

 const getStatusIcon = (status) => {
  switch (status) {
   case 'profit': return '✅';
   case 'loss': return '❌';
   default: return '➖';
  }
 };

 const getStatusClass = (status) => {
  switch (status) {
   case 'profit': return 'status-profit';
   case 'loss': return 'status-loss';
   default: return 'status-neutral';
  }
 };

 if (loading) {
  return <div className="loader">Ładowanie analizy FIFO...</div>;
 }

 if (error) {
  return <div className="error">{error}</div>;
 }

 if (!analysis) {
  return <div className="card">Brak danych do wyświetlenia</div>;
 }

 return (
  <div className="profit-analysis">
   <div className="page-header">
    <h2>📈 Analiza Zysków/Strat (FIFO)</h2>
    <div className="year-selector">
     <label>Okres:</label>
     <select
      value={selectedYear}
      onChange={(e) => setSelectedYear(e.target.value)}
     >
      {getAvailableYears().map(year => (
       <option key={year} value={year}>
        {year === 'all' ? 'Wszystkie lata' : year}
       </option>
      ))}
     </select>
    </div>
   </div>

   {/* Podsumowanie globalne */}
   <div className="summary-cards">
    <div className={`summary-card ${analysis.summary.netProfit >= 0 ? 'positive' : 'negative'}`}>
     <h3>Zysk/Strata netto</h3>
     <div className="value">{formatCurrency(analysis.summary.netProfit)}</div>
     <small>(po prowizjach)</small>
    </div>

    <div className="summary-card">
     <h3>Zrealizowany P/L</h3>
     <div className={`value ${analysis.summary.totalRealizedProfit >= 0 ? 'positive' : 'negative'}`}>
      {formatCurrency(analysis.summary.totalRealizedProfit)}
     </div>
     <small>(przed prowizjami)</small>
    </div>

    <div className="summary-card">
     <h3>Prowizje łącznie</h3>
     <div className="value negative">{formatCurrency(analysis.summary.totalFees)}</div>
     <small>(koszt)</small>
    </div>

    <div className="summary-card">
     <h3>Wartość w posiadaniu</h3>
     <div className="value">{formatCurrency(analysis.summary.totalUnrealizedCost)}</div>
     <small>(koszt zakupu)</small>
    </div>
   </div>

   {/* Legenda */}
   <div className="legend">
    <span className="legend-item">✅ Zysk</span>
    <span className="legend-item">❌ Strata</span>
    <span className="legend-item">➖ Neutralnie</span>
   </div>

   {/* Tabela analizy */}
   <div className="analysis-table-container">
    <table className="analysis-table">
     <thead>
      <tr>
       <th>Status</th>
       <th>Kryptowaluta</th>
       <th>Zysk/Strata netto</th>
       <th>W posiadaniu</th>
       <th>Śr. cena zakupu</th>
       <th>Break-even</th>
       <th>Szczegóły</th>
      </tr>
     </thead>
     <tbody>
      {analysis.analysis.map((item) => (
       <React.Fragment key={item.crypto}>
        <tr className={getStatusClass(item.status)}>
         <td className="status-cell">{getStatusIcon(item.status)}</td>
         <td className="crypto-name"><strong>{item.crypto}</strong></td>
         <td className={item.netProfit >= 0 ? 'positive' : 'negative'}>
          <strong>{formatCurrency(item.netProfit)}</strong>
         </td>
         <td>
          {item.holdingsAmount > 0
           ? formatNumber(item.holdingsAmount) + ' ' + item.crypto
           : <span className="muted">Brak</span>
          }
         </td>
         <td>
          {item.avgBuyRate > 0
           ? formatCurrency(item.avgBuyRate)
           : <span className="muted">-</span>
          }
         </td>
         <td className="break-even">
          {item.holdingsAmount > 0 && item.breakEvenRate > 0 ? (
           <div>
            <strong>{formatCurrency(item.breakEvenRate)}</strong>
            {item.status === 'loss' && (
             <div className="break-even-hint">
              Sprzedaj po tej cenie żeby wyjść na zero
             </div>
            )}
           </div>
          ) : (
           <span className="muted">-</span>
          )}
         </td>
         <td>
          <button
           className="btn-details"
           onClick={() => setExpandedCrypto(expandedCrypto === item.crypto ? null : item.crypto)}
          >
           {expandedCrypto === item.crypto ? '▲ Zwiń' : '▼ Rozwiń'}
          </button>
         </td>
        </tr>

        {expandedCrypto === item.crypto && (
         <tr className="details-row">
          <td colSpan="7">
           <div className="details-grid">
            <div className="detail-item">
             <label>Kupiono łącznie:</label>
             <span>{formatNumber(item.totalBought)} {item.crypto}</span>
            </div>
            <div className="detail-item">
             <label>Sprzedano łącznie:</label>
             <span>{formatNumber(item.totalSold)} {item.crypto}</span>
            </div>
            <div className="detail-item">
             <label>Wydano na zakupy:</label>
             <span>{formatCurrency(item.totalSpent)}</span>
            </div>
            <div className="detail-item">
             <label>Uzyskano ze sprzedaży:</label>
             <span>{formatCurrency(item.totalEarned)}</span>
            </div>
            <div className="detail-item">
             <label>Zrealizowany P/L:</label>
             <span className={item.realizedProfit >= 0 ? 'positive' : 'negative'}>
              {formatCurrency(item.realizedProfit)}
             </span>
            </div>
            <div className="detail-item">
             <label>Prowizje (crypto):</label>
             <span>{formatNumber(item.feesCrypto)} {item.crypto}</span>
            </div>
            <div className="detail-item">
             <label>Prowizje (PLN):</label>
             <span>{formatCurrency(item.feesPLN)}</span>
            </div>
            <div className="detail-item">
             <label>Prowizje łącznie (PLN):</label>
             <span className="negative">{formatCurrency(item.feesValuePLN)}</span>
            </div>
            <div className="detail-item">
             <label>Koszt w posiadaniu:</label>
             <span>{formatCurrency(item.holdingsCost)}</span>
            </div>
           </div>
          </td>
         </tr>
        )}
       </React.Fragment>
      ))}
     </tbody>
    </table>
   </div>

   {/* Wyjaśnienie */}
   <div className="info-box">
    <h4>ℹ️ Jak to działa?</h4>
    <ul>
     <li><strong>Metoda FIFO</strong> (First In, First Out) - przy sprzedaży najpierw "zużywamy" najstarsze zakupy</li>
     <li><strong>Zysk/Strata netto</strong> = Zrealizowany zysk - Prowizje</li>
     <li><strong>Break-even</strong> = (Koszt pozostałych + Straty do odrobienia + Prowizje) / Ilość w posiadaniu</li>
     <li><strong>Prowizje crypto</strong> są przeliczane na PLN według średniej ceny zakupu</li>
    </ul>
   </div>
  </div>
 );
};

export default ProfitAnalysisPage;
