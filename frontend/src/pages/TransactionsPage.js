import React, { useState, useEffect } from 'react';
import api from '../services/api';
import './TransactionsPage.css';

function TransactionsPage() {
 const [transactions, setTransactions] = useState([]);
 const [loading, setLoading] = useState(true);
 const [filters, setFilters] = useState({
  market: '',
  type: '',
  date_from: '',
  date_to: ''
 });
 const [pagination, setPagination] = useState({
  offset: 0,
  limit: 50,
  total: 0,
  pages: 1
 });

 // Modal state
 const [showModal, setShowModal] = useState(false);
 const [modalMode, setModalMode] = useState('create'); // 'create' or 'edit'
 const [currentTransaction, setCurrentTransaction] = useState(null);
 const [formData, setFormData] = useState({
  market: '',
  datetime: '',
  type: 'buy',
  order_type: 'maker',
  rate: '',
  amount: '',
  value: '',
  notes: ''
 });

 useEffect(() => {
  loadTransactions();
 }, [filters, pagination.offset]);

 const loadTransactions = async () => {
  setLoading(true);
  try {
   const params = {
    ...filters,
    limit: pagination.limit,
    offset: pagination.offset
   };

   const data = await api.getTransactions(params);

   setTransactions(data.transactions || []); // Zabezpieczenie przed undefined
   setPagination(prev => ({
    ...prev,
    total: data.total || 0,
    pages: data.pages || 1
   }));
  } catch (error) {
   console.error('Błąd podczas ładowania transakcji:', error);
   setTransactions([]); // Ustaw pustą tablicę w przypadku błędu
  } finally {
   setLoading(false);
  }
 };

 const handleFilterChange = (e) => {
  const { name, value } = e.target;
  setFilters(prev => ({
   ...prev,
   [name]: value
  }));
  setPagination(prev => ({ ...prev, offset: 0 }));
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
   notes: ''
  });
  setShowModal(true);
 };

 const openEditModal = (transaction) => {
  setModalMode('edit');
  setCurrentTransaction(transaction);
  setFormData({
   market: transaction.market,
   datetime: transaction.datetime.replace(' ', 'T').slice(0, 16),
   type: transaction.type,
   order_type: transaction.order_type,
   rate: transaction.rate,
   amount: transaction.amount,
   value: transaction.value,
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
 };

 const handleSubmit = async (e) => {
  e.preventDefault();

  try {
   const dataToSend = {
    ...formData,
    datetime: formData.datetime.replace('T', ' ') + ':00'
   };

   if (modalMode === 'create') {
    await api.createTransaction(dataToSend);
    alert('Transakcja została dodana!');
   } else {
    await api.updateTransaction(currentTransaction.id, dataToSend);
    alert('Transakcja została zaktualizowana!');
   }

   closeModal();
   loadTransactions();
  } catch (error) {
   alert('Błąd: ' + (error.response?.data?.message || error.message));
  }
 };

 const handleDelete = async (id) => {
  if (!window.confirm('Czy na pewno chcesz usunąć tę transakcję?')) {
   return;
  }

  try {
   await api.deleteTransaction(id);
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
    <h2>Historia Transakcji</h2>
    <button className="btn-add" onClick={openCreateModal}>
     ➕ Dodaj transakcję
    </button>
   </div>

   <div className="filters">
    <div className="filter-group">
     <label>Rynek:</label>
     <select name="market" value={filters.market} onChange={handleFilterChange}>
      <option value="">Wszystkie</option>
      <option value="BTC-PLN">BTC-PLN</option>
      <option value="ETH-PLN">ETH-PLN</option>
      <option value="LTC-PLN">LTC-PLN</option>
      <option value="BCC-PLN">BCC-PLN</option>
      <option value="BCH-PLN">BCH-PLN</option>
      <option value="LSK-PLN">LSK-PLN</option>
      <option value="DASH-PLN">DASH-PLN</option>
      <option value="GAME-PLN">GAME-PLN</option>
     </select>
    </div>

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
       <th>Data</th>
       <th>Rynek</th>
       <th>Typ</th>
       <th>Rodzaj</th>
       <th>Kurs</th>
       <th>Ilość</th>
       <th>Wartość</th>
       <th>Notatki</th>
       <th>Akcje</th>
      </tr>
     </thead>
     <tbody>
      {transactions.map(tx => (
       <tr key={tx.id}>
        <td>{new Date(tx.datetime).toLocaleString('pl-PL')}</td>
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
        <td className="notes">{tx.notes || '-'}</td>
        <td className="actions">
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
      Strona {Math.floor(pagination.offset / pagination.limit) + 1} / {pagination.pages}
     </span>
     <button onClick={nextPage} disabled={pagination.offset + pagination.limit >= pagination.total}>
      Następna →
     </button>
    </div>
   </div>

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
         <select name="order_type" value={formData.order_type} onChange={handleFormChange} required>
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
          type="number"
          step="0.00000001"
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

       <div className="form-group">
        <label>Notatki</label>
        <textarea
         name="notes"
         value={formData.notes}
         onChange={handleFormChange}
         placeholder="Dodatkowe informacje, np. prowizja sieciowa, transfer z innej giełdy..."
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