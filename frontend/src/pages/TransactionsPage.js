import React, { useState, useEffect, useCallback } from 'react';
import { getTransactions, createTransaction, updateTransaction, deleteTransaction, getTransactionSummary, setVerificationStatus } from '../services/api';
import * as XLSX from 'xlsx';
import './TransactionsPage.css';

function TransactionsPage({ selectedMarket }) {
 const [transactions, setTransactions] = useState([]);
 const [loading, setLoading] = useState(true);
 const [filters, setFilters] = useState({
  type: '',
  date_from: '',
  date_to: ''
 });
 const [summary, setSummary] = useState(null);
 const [sort, setSort] = useState({ by: 'datetime', dir: 'DESC' });
 const [pagination, setPagination] = useState({
  offset: 0,
  limit: 50,
  total: 0,
  pages: 1
 });

 // Modal state
 const [showModal, setShowModal] = useState(false);
 const [modalMode, setModalMode] = useState('create');
 const [currentTransaction, setCurrentTransaction] = useState(null);
 const [formData, setFormData] = useState({
  market: '',
  datetime: '',
  type: 'buy',
  order_type: 'maker',
  rate: '',
  amount: '',
  value: '',
  fee_amount: '',
  fee_currency: 'crypto',
  notes: ''
 });

 const handleSort = (column) => {
  setSort(prev => ({
   by: column,
   dir: prev.by === column && prev.dir === 'DESC' ? 'ASC' : 'DESC'
  }));
  setPagination(prev => ({ ...prev, offset: 0 }));
 };

 const loadTransactions = useCallback(async () => {
  setLoading(true);
  try {
   const params = {
    ...filters,
    ...(selectedMarket ? { market: selectedMarket } : {}),
    sort_by: sort.by,
    sort_dir: sort.dir,
    limit: pagination.limit,
    offset: pagination.offset
   };

   const data = await getTransactions(params);
   setTransactions(data.transactions || []); // Zabezpieczenie przed undefined
   setPagination(prev => ({
    ...prev,
    total: data.pagination?.total || 0,
    pages: data.pagination?.pages || 1
   }));
  } catch (error) {
   console.error('Błąd podczas ładowania transakcji:', error);
   setTransactions([]); // Ustaw pustą tablicę w przypadku błędu
  } finally {
   setLoading(false);
  }
 }, [filters, selectedMarket, sort, pagination.limit, pagination.offset]);

 useEffect(() => {
  loadTransactions();
 }, [loadTransactions]);

 useEffect(() => {
  const params = {
   ...filters,
   ...(selectedMarket ? { market: selectedMarket } : {}),
  };
  getTransactionSummary(params)
   .then(data => setSummary(data.success ? data : null))
   .catch(() => setSummary(null));
 }, [filters, selectedMarket]);

 const handleFilterChange = (e) => {
  const { name, value } = e.target;
  setFilters(prev => ({
   ...prev,
   [name]: value
  }));
  setPagination(prev => ({ ...prev, offset: 0 }));
 };

 const handleExport = async (format) => {
  try {
   const params = {
    ...filters,
    ...(selectedMarket ? { market: selectedMarket } : {}),
    sort_by: sort.by,
    sort_dir: sort.dir,
    limit: 99999,
    offset: 0
   };
   const data = await getTransactions(params);
   const rows = (data.transactions || []).map(tx => ({
    'Data':            tx.datetime,
    'Rynek':           tx.market,
    'Typ':             tx.type === 'buy' ? 'Kupno' : 'Sprzedaż',
    'Rodzaj':          tx.order_type,
    'Kurs':            parseFloat(tx.rate),
    'Ilość':           parseFloat(tx.amount),
    'Wartość':         parseFloat(tx.value),
    'Prowizja PLN':    tx.fee_pln || 0,
    'Prowizja krypto': tx.fee_crypto || 0,
    'Waluta prowizji': tx.fee_crypto_currency || '',
    'Netto':           tx.net_received != null ? parseFloat(tx.net_received) : '',
    'Waluta netto':    tx.net_received_currency || '',
    'Saldo po':        tx.balance_after != null ? parseFloat(tx.balance_after) : '',
    'Waluta salda':    tx.balance_after_currency || '',
   }));

   const ws = XLSX.utils.json_to_sheet(rows);
   const wb = XLSX.utils.book_new();
   XLSX.utils.book_append_sheet(wb, ws, 'Transakcje');

   const filename = `transakcje_${selectedMarket || 'wszystkie'}_${new Date().toISOString().slice(0,10)}`;
   if (format === 'xlsx') {
    XLSX.writeFile(wb, `${filename}.xlsx`);
   } else {
    // UTF-8 BOM zapewnia poprawne polskie znaki w Excel
    const csv = XLSX.utils.sheet_to_csv(ws, { FS: ';' });
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${filename}.csv`;
    a.click();
    URL.revokeObjectURL(url);
   }
  } catch (err) {
   console.error('Błąd eksportu:', err);
  }
 };

 const nextPage = () => {
  if (pagination.offset + pagination.limit < pagination.total) {
   setPagination(prev => ({
    ...prev,
    offset: prev.offset + prev.limit
   }));
  }
 };

 const prevPage = () => {
  if (pagination.offset > 0) {
   setPagination(prev => ({
    ...prev,
    offset: Math.max(0, prev.offset - prev.limit)
   }));
  }
 };

 // Wyciągnij crypto z rynku (np. BTC-PLN -> BTC)
 const getCryptoFromMarket = (market) => {
  if (!market || !market.includes('-')) return 'CRYPTO';
  return market.split('-')[0];
 };

 // Formatowanie prowizji do wyświetlenia
 const formatFee = (tx) => {
  const parts = [];

  if (tx.fee_pln && tx.fee_pln > 0) {
   parts.push(`${parseFloat(tx.fee_pln).toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} PLN`);
  }

  if (tx.fee_crypto && tx.fee_crypto > 0) {
   parts.push(`${parseFloat(tx.fee_crypto).toLocaleString('pl-PL', { minimumFractionDigits: 8, maximumFractionDigits: 8 })} ${tx.fee_crypto_currency || 'CRYPTO'}`);
  }

  if (parts.length > 0) return parts.join(' + ');

  // Prowizja zapisana jako 0 — pokaż z odpowiednim formatowaniem
  if (tx.has_real_fee && tx.fees && tx.fees.length > 0) {
   const zeroFee = tx.fees[0];
   const decimals = zeroFee.currency === 'PLN' ? 2 : 8;
   return `${(0).toLocaleString('pl-PL', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })} ${zeroFee.currency}`;
  }

  return parseFloat(tx.rate) === 0 ? '0' : '-';
 };

 // Sprawdź czy transakcja ma prowizję
 const hasFee = (tx) => {
  return (tx.fee_pln && tx.fee_pln > 0) || (tx.fee_crypto && tx.fee_crypto > 0);
 };

 // Modal functions
 const openCreateModal = () => {
  setModalMode('create');
  setFormData({
   market: '',
   datetime: new Date().toISOString().slice(0, 16),
   type: 'buy',
   order_type: 'maker',
   rate: '',
   amount: '',
   value: '',
   fee_amount: '',
   fee_currency: 'crypto',
   notes: ''
  });
  setShowModal(true);
 };

 const openEditModal = (transaction) => {
  setModalMode('edit');
  setCurrentTransaction(transaction);

  // Określ walutę prowizji na podstawie istniejących danych
  let feeCurrency = 'crypto';
  let feeAmount = '';

  if (transaction.fee_pln > 0) {
   feeCurrency = 'PLN';
   feeAmount = parseFloat(transaction.fee_pln).toFixed(2);
  } else if (transaction.fee_crypto > 0) {
   feeCurrency = 'crypto';
   feeAmount = parseFloat(transaction.fee_crypto).toFixed(8);
  } else if (transaction.has_real_fee && transaction.fees.length > 0) {
   // Prowizja zapisana jako 0
   const zeroFee = transaction.fees[0];
   feeCurrency = zeroFee.currency === 'PLN' ? 'PLN' : 'crypto';
   feeAmount = zeroFee.currency === 'PLN' ? '0.00' : '0.00000000';
  }

  setFormData({
   market: transaction.market,
   datetime: transaction.datetime.replace(' ', 'T').slice(0, 16),
   type: transaction.type,
   order_type: transaction.order_type,
   rate: transaction.rate,
   amount: parseFloat(transaction.amount).toFixed(8),
   value: transaction.value,
   fee_amount: feeAmount,
   fee_currency: feeCurrency,
   notes: transaction.notes || ''
  });
  setShowModal(true);
 };

 const closeModal = () => {
  setShowModal(false);
  setCurrentTransaction(null);
 };

 const handleFormChange = (e) => {
  const { name, value } = e.target;
  setFormData(prev => ({
   ...prev,
   [name]: value
  }));

  // Auto-calculate value when rate or amount changes
  if (name === 'rate' || name === 'amount') {
   const rate = name === 'rate' ? parseFloat(value) : parseFloat(formData.rate);
   const amount = name === 'amount' ? parseFloat(value) : parseFloat(formData.amount);

   if (!isNaN(rate) && !isNaN(amount)) {
    setFormData(prev => ({
     ...prev,
     value: (rate * amount).toFixed(2)
    }));
   }
  }

  // Auto-switch fee currency based on transaction type
  if (name === 'type') {
   setFormData(prev => ({
    ...prev,
    fee_currency: value === 'buy' ? 'crypto' : 'PLN'
   }));
  }
 };

 const handleSubmit = async (e) => {
  e.preventDefault();

  try {
   const dataToSend = {
    ...formData,
    datetime: formData.datetime.replace('T', ' ') + ':00',
    amount: parseFloat(String(formData.amount).replace(',', '.')),
    fee_amount: formData.fee_amount !== '' ? parseFloat(String(formData.fee_amount).replace(',', '.')) : null,
    fee_currency: formData.fee_currency === 'crypto'
     ? getCryptoFromMarket(formData.market)
     : 'PLN'
   };

   if (modalMode === 'create') {
    await createTransaction(dataToSend);
    alert('Transakcja została dodana!');
   } else {
    await updateTransaction(currentTransaction.id, dataToSend);
    alert('Transakcja została zaktualizowana!');
   }

   closeModal();
   loadTransactions();
  } catch (error) {
   alert('Błąd: ' + (error.response?.data?.message || error.message));
  }
 };

 const VERIFY_CYCLE = ['unverified', 'ok', 'needs_review'];
 const VERIFY_LABELS = { unverified: '○', ok: '✓', needs_review: '!' };
 const VERIFY_TITLES = { unverified: 'Niezweryfikowana', ok: 'Zweryfikowana OK', needs_review: 'Wymaga sprawdzenia' };

 const handleVerify = async (tx) => {
  const next = VERIFY_CYCLE[(VERIFY_CYCLE.indexOf(tx.verification_status) + 1) % VERIFY_CYCLE.length];
  try {
   await setVerificationStatus(tx.id, next);
   setTransactions(prev => prev.map(t => t.id === tx.id ? { ...t, verification_status: next } : t));
  } catch (error) {
   console.error('Błąd zmiany statusu:', error);
  }
 };

 const handleDelete = async (id) => {
  if (!window.confirm('Czy na pewno chcesz usunąć tę transakcję?')) {
   return;
  }

  try {
   await deleteTransaction(id);
   alert('Transakcja została usunięta!');
   loadTransactions();
  } catch (error) {
   alert('Błąd: ' + (error.response?.data?.message || error.message));
  }
 };

 if (loading && transactions.length === 0) {
  return <div className="loading">Ładowanie transakcji...</div>;
 }

 return (
  <div className="transactions-page">
   <div className="page-header">
    <h2>
     {selectedMarket
      ? `Transakcje: ${selectedMarket}`
      : 'Wszystkie transakcje'}
    </h2>
    <div className="header-actions">
     <button className="btn-export" onClick={() => handleExport('xlsx')} title="Eksportuj do XLSX">⬇ XLSX</button>
     <button className="btn-export" onClick={() => handleExport('csv')}  title="Eksportuj do CSV">⬇ CSV</button>
     <button className="btn-add" onClick={openCreateModal}>➕ Dodaj transakcję</button>
    </div>
   </div>

   <div className="filters">
    <div className="filter-group">
     <label>Typ:</label>
     <select name="type" value={filters.type} onChange={handleFilterChange}>
      <option value="">Wszystkie</option>
      <option value="buy">Kupno</option>
      <option value="sell">Sprzedaż</option>
     </select>
    </div>

    <div className="filter-group">
     <label>Data od:</label>
     <input
      type="date"
      name="date_from"
      value={filters.date_from}
      onChange={handleFilterChange}
     />
    </div>

    <div className="filter-group">
     <label>Data do:</label>
     <input
      type="date"
      name="date_to"
      value={filters.date_to}
      onChange={handleFilterChange}
     />
    </div>
   </div>

   <div className="table-container">
    <table className="transactions-table">
     <thead>
      <tr>
       {[
        { label: 'Data',    col: 'datetime' },
        { label: 'Rynek',   col: 'market' },
        { label: 'Typ',     col: 'type' },
        { label: 'Rodzaj',  col: null },
        { label: 'Kurs',    col: 'rate',   cls: 'number' },
        { label: 'Ilość',   col: 'amount', cls: 'number' },
        { label: 'Wartość', col: 'value',  cls: 'number' },
        { label: 'Prowizja',col: null,     cls: 'number' },
        { label: 'Netto',   col: null,     cls: 'number' },
        { label: 'Saldo',   col: null,     cls: 'number' },
       ].map(({ label, col, cls }) => (
        <th
         key={label}
         className={[cls, col ? 'sortable' : ''].filter(Boolean).join(' ')}
         onClick={col ? () => handleSort(col) : undefined}
        >
         {label}
         {col && sort.by === col && (
          <span className="sort-indicator">{sort.dir === 'DESC' ? ' ▼' : ' ▲'}</span>
         )}
         {col && sort.by !== col && <span className="sort-indicator-idle"> ⇅</span>}
        </th>
       ))}
       <th style={{textAlign:'center'}} title="Notatki">💬</th>
       <th style={{textAlign:'center'}}>Akcje</th>
      </tr>
     </thead>
     <tbody>
      {transactions.map(tx => (
       <tr key={tx.id} className={`verify-${tx.verification_status || 'unverified'}`}>
        <td>{new Date(tx.datetime).toLocaleString('pl-PL', {
         day: '2-digit',
         month: '2-digit',
         year: 'numeric',
         hour: '2-digit',
         minute: '2-digit'
        })}</td>
        <td>
         <span className="market-badge">{tx.market}</span>
        </td>
        <td>
         <span className={`type-badge ${tx.type}`}>
          {tx.type === 'buy' ? '🟢 Kupno' : '🔴 Sprzedaż'}
         </span>
        </td>
        <td>
         <span className="order-badge">{tx.order_type}</span>
        </td>
        <td className="number">{parseFloat(tx.rate).toLocaleString('pl-PL', { minimumFractionDigits: 2 })}</td>
        <td className="number">{parseFloat(tx.amount).toLocaleString('pl-PL', { minimumFractionDigits: 8 })}</td>
        <td className="number value">{parseFloat(tx.value).toLocaleString('pl-PL', { minimumFractionDigits: 2 })} PLN</td>
        <td className={`number fee ${hasFee(tx) ? 'has-fee' : ''}`}>
         {formatFee(tx)}
        </td>
        <td className="number net-received">
         {tx.net_received != null
          ? <span title={!tx.has_real_fee && parseFloat(tx.rate) !== 0 ? 'Brak danych o prowizji — kwota brutto' : undefined}
                  className={!tx.has_real_fee && parseFloat(tx.rate) !== 0 ? 'net-no-fee' : ''}>
              {parseFloat(tx.net_received).toLocaleString('pl-PL', {
               minimumFractionDigits: tx.net_received_currency === 'PLN' ? 2 : 8,
               maximumFractionDigits: tx.net_received_currency === 'PLN' ? 2 : 8,
              })} <span className="currency-label">{tx.net_received_currency}</span>
             </span>
          : <span className="no-data">—</span>}
        </td>
        <td className="number balance-after">
         {tx.balance_after != null
          ? <span
              className={
                tx.balance_after_source === 'calculated' ? 'balance-calculated' :
                tx.balance_after_source === 'inferred'   ? 'balance-inferred'   : ''
              }
              title={
                tx.balance_after_source === 'calculated' ? 'Saldo wyliczone z historii transakcji — może się różnić od salda giełdowego' :
                tx.balance_after_source === 'inferred'   ? 'Saldo przybliżone — wyliczone z następnej transakcji. Wymaga weryfikacji!' :
                'Saldo z danych giełdowych'
              }
            >
              {tx.balance_after_source === 'calculated' && <span className="calc-indicator">~</span>}
              {tx.balance_after_source === 'inferred'   && <span className="calc-indicator">≈</span>}
              {parseFloat(tx.balance_after).toLocaleString('pl-PL', {
               minimumFractionDigits: tx.balance_after_currency === 'PLN' ? 2 : 8,
               maximumFractionDigits: tx.balance_after_currency === 'PLN' ? 2 : 8,
              })} <span className="currency-label">{tx.balance_after_currency}</span>
             </span>
          : <span className="no-data">—</span>}
        </td>
        <td className="notes-cell">
         {tx.notes ? (
          <span className="notes-icon" title={tx.notes}>
           💬
          </span>
         ) : (
          <span className="no-notes">-</span>
         )}
        </td>
        <td className="actions">
         <button
          className={`btn-verify status-${tx.verification_status || 'unverified'}`}
          onClick={() => handleVerify(tx)}
          title={VERIFY_TITLES[tx.verification_status || 'unverified']}
         >
          {VERIFY_LABELS[tx.verification_status || 'unverified']}
         </button>
         <button className="btn-edit" onClick={() => openEditModal(tx)} title="Edytuj">
          ✏️
         </button>
         <button className="btn-delete" onClick={() => handleDelete(tx.id)} title="Usuń">
          🗑️
         </button>
        </td>
       </tr>
      ))}
     </tbody>
    </table>
   </div>

   <div className="pagination">
    <div className="pagination-info">
     Pokazuję {pagination.offset + 1} - {Math.min(pagination.offset + pagination.limit, pagination.total)} z {pagination.total} transakcji
    </div>
    <div className="pagination-buttons">
     <button onClick={prevPage} disabled={pagination.offset === 0}>
      ← Poprzednia
     </button>
     <span className="page-info">
      Strona {Math.floor(pagination.offset / pagination.limit) + 1} / {Math.max(1, pagination.pages)}
     </span>
     <button onClick={nextPage} disabled={pagination.offset + pagination.limit >= pagination.total}>
      Następna →
     </button>
    </div>
   </div>

   {/* Podsumowanie */}
   {summary && (summary.summary.buy || summary.summary.sell) && (
    <div className="tx-summary">
     <div className="tx-summary-title">Podsumowanie okresu</div>
     <div className="tx-summary-grid">

      {summary.summary.buy && (
       <div className="tx-summary-card buy">
        <div className="tx-summary-card-label">Kupno</div>
        <div className="tx-summary-card-row">
         <span>Liczba transakcji</span>
         <strong>{summary.summary.buy.count}</strong>
        </div>
        <div className="tx-summary-card-row">
         <span>Łącznie kupiono</span>
         <strong>{parseFloat(summary.summary.buy.total_amount).toLocaleString('pl-PL', { minimumFractionDigits: 8 })}</strong>
        </div>
        <div className="tx-summary-card-row">
         <span>Zapłacono łącznie</span>
         <strong>{parseFloat(summary.summary.buy.total_value).toLocaleString('pl-PL', { minimumFractionDigits: 2 })} PLN</strong>
        </div>
        <div className="tx-summary-card-row">
         <span>Średni kurs kupna</span>
         <strong>{parseFloat(summary.summary.buy.avg_rate).toLocaleString('pl-PL', { minimumFractionDigits: 2 })} PLN</strong>
        </div>
       </div>
      )}

      {summary.summary.sell && (
       <div className="tx-summary-card sell">
        <div className="tx-summary-card-label">Sprzedaż</div>
        <div className="tx-summary-card-row">
         <span>Liczba transakcji</span>
         <strong>{summary.summary.sell.count}</strong>
        </div>
        <div className="tx-summary-card-row">
         <span>Łącznie sprzedano</span>
         <strong>{parseFloat(summary.summary.sell.total_amount).toLocaleString('pl-PL', { minimumFractionDigits: 8 })}</strong>
        </div>
        <div className="tx-summary-card-row">
         <span>Otrzymano łącznie</span>
         <strong>{parseFloat(summary.summary.sell.total_value).toLocaleString('pl-PL', { minimumFractionDigits: 2 })} PLN</strong>
        </div>
        <div className="tx-summary-card-row">
         <span>Średni kurs sprzedaży</span>
         <strong>{parseFloat(summary.summary.sell.avg_rate).toLocaleString('pl-PL', { minimumFractionDigits: 2 })} PLN</strong>
        </div>
       </div>
      )}

      <div className={`tx-summary-card balance ${summary.balance >= 0 ? 'profit' : 'loss'}`}>
       <div className="tx-summary-card-label">Bilans okresu</div>
       <div className="tx-summary-balance-value">
        {summary.balance >= 0 ? '▲' : '▼'}{' '}
        {Math.abs(summary.balance).toLocaleString('pl-PL', { minimumFractionDigits: 2 })} PLN
       </div>
       <div className="tx-summary-balance-desc">
        {summary.balance >= 0
         ? 'Wpływy ze sprzedaży przewyższają koszty kupna'
         : 'Koszty kupna przewyższają wpływy ze sprzedaży'}
       </div>
       {(() => {
        const boughtAmt = summary.summary.buy?.total_amount  || 0;
        const soldAmt   = summary.summary.sell?.total_amount || 0;
        const remaining = boughtAmt - soldAmt;
        if (summary.balance < 0 && remaining > 0.000000001) {
         const breakEven = Math.abs(summary.balance) / remaining;
         const crypto = selectedMarket ? selectedMarket.split('-')[0] : 'krypto';
         return (
          <div className="tx-summary-breakeven">
           <div className="tx-summary-breakeven-label">Próg rentowności</div>
           <div className="tx-summary-breakeven-value">
            {breakEven.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} PLN
           </div>
           <div className="tx-summary-breakeven-desc">
            Sprzedaj pozostałe{' '}
            {remaining.toLocaleString('pl-PL', { minimumFractionDigits: 8, maximumFractionDigits: 8 })} {crypto}{' '}
            po tym kursie, aby wyjść na zero w tym okresie
           </div>
          </div>
         );
        }
        if (summary.balance < 0 && remaining <= 0.000000001) {
         return <div className="tx-summary-balance-note">Brak pozostałego krypto — strata zrealizowana</div>;
        }
        return null;
       })()}
       <div className="tx-summary-balance-note">
        * Bilans kasowy okresu — zmień zakres dat lub wybierz krypto z menu
       </div>
      </div>

     </div>
    </div>
   )}

   {/* Modal */}
   {showModal && (
    <div className="modal-overlay" onClick={closeModal}>
     <div className="modal-content" onClick={(e) => e.stopPropagation()}>
      <div className="modal-header">
       <h3>{modalMode === 'create' ? '➕ Dodaj transakcję' : '✏️ Edytuj transakcję'}</h3>
       <button className="modal-close" onClick={closeModal}>✕</button>
      </div>

      <form onSubmit={handleSubmit}>
       <div className="form-row">
        <div className="form-group">
         <label>Rynek *</label>
         <input
          type="text"
          name="market"
          value={formData.market}
          onChange={handleFormChange}
          placeholder="np. BTC-PLN"
          required
         />
        </div>

        <div className="form-group">
         <label>Data i czas *</label>
         <input
          type="datetime-local"
          name="datetime"
          value={formData.datetime}
          onChange={handleFormChange}
          required
         />
        </div>
       </div>

       <div className="form-row">
        <div className="form-group">
         <label>Typ transakcji *</label>
         <select name="type" value={formData.type} onChange={handleFormChange} required>
          <option value="buy">Kupno</option>
          <option value="sell">Sprzedaż</option>
         </select>
        </div>

        <div className="form-group">
         <label>Rodzaj zlecenia *</label>
         <select name="order_type" value={formData.order_order} onChange={handleFormChange} required>
          <option value="maker">Maker</option>
          <option value="taker">Taker</option>
         </select>
        </div>
       </div>

       <div className="form-row">
        <div className="form-group">
         <label>Kurs *</label>
         <input
          type="number"
          step="0.00000001"
          name="rate"
          value={formData.rate}
          onChange={handleFormChange}
          placeholder="0.00"
          required
         />
        </div>

        <div className="form-group">
         <label>Ilość *</label>
         <input
          type="text"
          name="amount"
          value={formData.amount}
          onChange={handleFormChange}
          placeholder="0.00000000"
          required
         />
        </div>

        <div className="form-group">
         <label>Wartość PLN *</label>
         <input
          type="number"
          step="0.01"
          name="value"
          value={formData.value}
          onChange={handleFormChange}
          placeholder="0.00"
          required
         />
        </div>
       </div>

       {/* Sekcja prowizji */}
       <div className="form-section">
        <h4>💰 Prowizja giełdowa</h4>
        <div className="form-row">
         <div className="form-group">
          <label>Kwota prowizji</label>
          <input
           type="text"
           name="fee_amount"
           value={formData.fee_amount}
           onChange={handleFormChange}
           placeholder="0.00000000"
          />
         </div>

         <div className="form-group">
          <label>Waluta prowizji</label>
          <select name="fee_currency" value={formData.fee_currency} onChange={handleFormChange}>
           <option value="crypto">
            {getCryptoFromMarket(formData.market) || 'Crypto'} (kryptowaluta)
           </option>
           <option value="PLN">PLN (złotówki)</option>
          </select>
         </div>
        </div>
        <p className="form-hint">
         💡 Na Zonda prowizja jest pobierana w walucie którą otrzymujesz:
         {formData.type === 'buy'
          ? ` przy kupnie w ${getCryptoFromMarket(formData.market) || 'crypto'}`
          : ' przy sprzedaży w PLN'}
        </p>
       </div>

       <div className="form-group">
        <label>Notatki</label>
        <textarea
         name="notes"
         value={formData.notes}
         onChange={handleFormChange}
         placeholder="Dodatkowe informacje..."
         rows="3"
        />
       </div>

       <div className="modal-actions">
        <button type="button" className="btn-cancel" onClick={closeModal}>
         Anuluj
        </button>
        <button type="submit" className="btn-save">
         {modalMode === 'create' ? 'Dodaj' : 'Zapisz'}
        </button>
       </div>
      </form>
     </div>
    </div>
   )}
  </div>
 );
}

export default TransactionsPage;
