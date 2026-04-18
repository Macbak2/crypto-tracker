import React, { useState, useEffect, useCallback } from 'react';
import { getStatistics, getYears } from '../services/api';

const StatisticsPage = () => {
 const [statistics, setStatistics] = useState(null);
 const [loading, setLoading] = useState(true);
 const [error, setError] = useState(null);
 const [selectedYear, setSelectedYear] = useState(null);
 const [availableYears, setAvailableYears] = useState([]);

 useEffect(() => {
  getYears().then(data => {
   const years = data.years || [];
   setAvailableYears(years);
   if (years.length > 0) setSelectedYear(years[0]);
  }).catch(() => {
   setAvailableYears([]);
   setSelectedYear(new Date().getFullYear());
  });
 }, []);

 const fetchStatistics = useCallback(async () => {
  if (selectedYear === null) return;
  setLoading(true);
  setError(null);

  try {
   const response = await getStatistics(selectedYear);

   if (response.success) {
    setStatistics(response);
   }
  } catch (err) {
   setError('Błąd podczas pobierania statystyk: ' + err.message);
  } finally {
   setLoading(false);
  }
 }, [selectedYear]);

 useEffect(() => {
  fetchStatistics();
 }, [fetchStatistics]);

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


 if (loading) {
  return <div className="loader"></div>;
 }

 if (error) {
  return <div className="error">{error}</div>;
 }

 if (!statistics) {
  return <div className="card">Brak danych do wyświetlenia</div>;
 }

 return (
  <div>
   <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
    <h2>Statystyki i podsumowania</h2>
    <div>
     <label style={{ marginRight: '10px', fontWeight: '500' }}>Rok:</label>
     <select
      value={selectedYear}
      onChange={(e) => setSelectedYear(parseInt(e.target.value))}
      style={{ padding: '8px', borderRadius: '4px', border: '1px solid #ddd' }}
     >
      {availableYears.map(year => (
       <option key={year} value={year}>{year}</option>
      ))}
     </select>
    </div>
   </div>

   {/* Główne statystyki */}
   <div className="stats-grid">
    <div className="stat-card">
     <h3>Całkowity wydatek</h3>
     <div className="value">{formatCurrency(statistics.summary.totalSpent)}</div>
    </div>

    <div className="stat-card">
     <h3>Całkowity przychód</h3>
     <div className="value">{formatCurrency(statistics.summary.totalEarned)}</div>
    </div>

    <div className="stat-card">
     <h3>Zysk/Strata</h3>
     <div className={`value ${statistics.summary.profitLoss >= 0 ? 'positive' : 'negative'}`}>
      {formatCurrency(statistics.summary.profitLoss)}
     </div>
    </div>

    <div className="stat-card">
     <h3>Liczba transakcji</h3>
     <div className="value">{statistics.summary.totalTransactions}</div>
    </div>
   </div>

   {/* Podsumowanie per kryptowaluta */}
   <div className="card">
    <h3>Podsumowanie per kryptowaluta</h3>
    <div style={{ overflowX: 'auto', marginTop: '15px' }}>
     <table className="table">
      <thead>
       <tr>
        <th>Kryptowaluta</th>
        <th>Kupiono</th>
        <th>Sprzedano</th>
        <th>Wydano (PLN)</th>
        <th>Zarobiono (PLN)</th>
        <th>Różnica (PLN)</th>
        <th>Transakcji</th>
       </tr>
      </thead>
      <tbody>
       {statistics.perCrypto.length === 0 ? (
        <tr>
         <td colSpan="7" style={{ textAlign: 'center', padding: '20px' }}>
          Brak danych
         </td>
        </tr>
       ) : (
        statistics.perCrypto.map((crypto, index) => {
         const profit = crypto.total_earned - crypto.total_spent;
         return (
          <tr key={index}>
           <td><strong>{crypto.crypto}</strong></td>
           <td>{formatNumber(crypto.total_bought)}</td>
           <td>{formatNumber(crypto.total_sold)}</td>
           <td>{formatCurrency(crypto.total_spent)}</td>
           <td>{formatCurrency(crypto.total_earned)}</td>
           <td style={{ color: profit >= 0 ? '#2e7d32' : '#c62828', fontWeight: '600' }}>
            {formatCurrency(profit)}
           </td>
           <td>{parseInt(crypto.buy_count) + parseInt(crypto.sell_count)}</td>
          </tr>
         );
        })
       )}
      </tbody>
     </table>
    </div>
   </div>

   {/* Prowizje */}
   {statistics.fees && statistics.fees.length > 0 && (
    <div className="card">
     <h3>Prowizje</h3>
     <div style={{ overflowX: 'auto', marginTop: '15px' }}>
      <table className="table">
       <thead>
        <tr>
         <th>Waluta</th>
         <th>Suma prowizji</th>
        </tr>
       </thead>
       <tbody>
        {statistics.fees.map((fee, index) => (
         <tr key={index}>
          <td><strong>{fee.currency}</strong></td>
          <td>{formatNumber(fee.total_fees)}</td>
         </tr>
        ))}
       </tbody>
      </table>
     </div>
    </div>
   )}

   {/* Podsumowanie miesięczne */}
   {statistics.monthly && statistics.monthly.length > 0 && (
    <div className="card">
     <h3>Podsumowanie miesięczne</h3>
     <div style={{ overflowX: 'auto', marginTop: '15px' }}>
      <table className="table">
       <thead>
        <tr>
         <th>Miesiąc</th>
         <th>Wydano (PLN)</th>
         <th>Zarobiono (PLN)</th>
         <th>Różnica (PLN)</th>
         <th>Transakcji</th>
        </tr>
       </thead>
       <tbody>
        {statistics.monthly.map((month, index) => {
         const profit = month.earned - month.spent;
         return (
          <tr key={index}>
           <td><strong>{month.month}</strong></td>
           <td>{formatCurrency(month.spent)}</td>
           <td>{formatCurrency(month.earned)}</td>
           <td style={{ color: profit >= 0 ? '#2e7d32' : '#c62828', fontWeight: '600' }}>
            {formatCurrency(profit)}
           </td>
           <td>{month.transaction_count}</td>
          </tr>
         );
        })}
       </tbody>
      </table>
     </div>
    </div>
   )}

   {/* Historia importów */}
   {statistics.importHistory && statistics.importHistory.length > 0 && (
    <div className="card">
     <h3>Historia importów</h3>
     <div style={{ overflowX: 'auto', marginTop: '15px' }}>
      <table className="table">
       <thead>
        <tr>
         <th>Data importu</th>
         <th>Nazwa pliku</th>
         <th>Typ</th>
         <th>Rekordów</th>
         <th>Okres danych</th>
        </tr>
       </thead>
       <tbody>
        {statistics.importHistory.map((item, index) => (
         <tr key={index}>
          <td>{new Date(item.imported_at).toLocaleString('pl-PL')}</td>
          <td>{item.file_name}</td>
          <td>
           <span className="badge badge-info">
            {item.file_type}
           </span>
          </td>
          <td>{item.records_count}</td>
          <td>
           {item.date_from && item.date_to
            ? `${item.date_from} - ${item.date_to}`
            : 'Brak danych'}
          </td>
         </tr>
        ))}
       </tbody>
      </table>
     </div>
    </div>
   )}
  </div>
 );
};

export default StatisticsPage;
