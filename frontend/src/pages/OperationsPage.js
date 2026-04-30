import React, { useState, useEffect, useCallback } from 'react';
import * as XLSX from 'xlsx';
import { getOperations, setOperationVerificationStatus } from '../services/api';
import './OperationsPage.css';

const VERIFY_CYCLE  = ['unverified', 'ok', 'needs_review'];
const VERIFY_LABELS = { unverified: '○', ok: '✓', needs_review: '!' };
const VERIFY_TITLES = { unverified: 'Niezweryfikowana', ok: 'Zweryfikowana OK', needs_review: 'Wymaga sprawdzenia' };

function OperationsPage() {
 const [operations, setOperations]   = useState([]);
 const [loading, setLoading]         = useState(true);
 const [types, setTypes]             = useState([]);
 const [currencies, setCurrencies]   = useState([]);
 const [filters, setFilters]         = useState({ operation_type: '', currency: '', date_from: '', date_to: '' });
 const [sort, setSort]               = useState({ by: 'datetime', dir: 'DESC' });
 const [pagination, setPagination]   = useState({ offset: 0, limit: 50, total: 0, pages: 1 });

 const loadOperations = useCallback(async () => {
  setLoading(true);
  try {
   const data = await getOperations({
    ...filters,
    sort_by:  sort.by,
    sort_dir: sort.dir,
    limit:    pagination.limit,
    offset:   pagination.offset,
   });
   setOperations(data.operations || []);
   setTypes(data.types || []);
   setCurrencies(data.currencies || []);
   setPagination(prev => ({ ...prev, total: data.pagination?.total || 0, pages: data.pagination?.pages || 1 }));
  } catch (e) {
   console.error(e);
  } finally {
   setLoading(false);
  }
 }, [filters, sort, pagination.limit, pagination.offset]);

 useEffect(() => { loadOperations(); }, [loadOperations]);

 const handleFilterChange = (e) => {
  const { name, value } = e.target;
  setFilters(prev => ({ ...prev, [name]: value }));
  setPagination(prev => ({ ...prev, offset: 0 }));
 };

 const handleSort = (col) => {
  setSort(prev => ({ by: col, dir: prev.by === col && prev.dir === 'DESC' ? 'ASC' : 'DESC' }));
  setPagination(prev => ({ ...prev, offset: 0 }));
 };

 const handleVerify = async (op) => {
  const next = VERIFY_CYCLE[(VERIFY_CYCLE.indexOf(op.verification_status || 'unverified') + 1) % VERIFY_CYCLE.length];
  try {
   await setOperationVerificationStatus(op.id, next);
   setOperations(prev => prev.map(o => o.id === op.id ? { ...o, verification_status: next } : o));
  } catch (e) {
   console.error('Błąd zmiany statusu:', e);
  }
 };

 const handleExport = async (format) => {
  const data = await getOperations({ ...filters, sort_by: sort.by, sort_dir: sort.dir, limit: 99999, offset: 0 });
  const rows = (data.operations || []).map(op => ({
   'Data':           op.datetime,
   'Typ operacji':   op.operation_type,
   'Kwota':          parseFloat(op.amount),
   'Waluta':         op.currency,
   'Saldo dostępne': op.balance_available != null ? parseFloat(op.balance_available) : '',
   'Saldo całkowite':op.balance_total != null ? parseFloat(op.balance_total) : '',
   'ID transakcji':  op.transaction_id || '',
   'Status':         op.verification_status || 'unverified',
  }));
  const ws = XLSX.utils.json_to_sheet(rows);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Operacje');
  const filename = `operacje_${new Date().toISOString().slice(0, 10)}`;
  if (format === 'xlsx') {
   XLSX.writeFile(wb, `${filename}.xlsx`);
  } else {
   const csv = XLSX.utils.sheet_to_csv(ws, { FS: ';' });
   const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
   const a = document.createElement('a');
   a.href = URL.createObjectURL(blob);
   a.download = `${filename}.csv`;
   a.click();
   URL.revokeObjectURL(a.href);
  }
 };

 const currentPage = Math.floor(pagination.offset / pagination.limit) + 1;
 const sortArrow = (col) => sort.by === col ? (sort.dir === 'ASC' ? ' ↑' : ' ↓') : '';

 const formatAmount = (op) => {
  const val = parseFloat(op.amount);
  const decimals = op.currency === 'PLN' ? 2 : 8;
  const formatted = Math.abs(val).toLocaleString('pl-PL', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
  const sign = val >= 0 ? '+' : '-';
  return <span className={val >= 0 ? 'amount-positive' : 'amount-negative'}>{sign}{formatted} {op.currency}</span>;
 };

 const formatBalance = (val, currency) => {
  if (val == null || parseFloat(val) === 0) return <span className="no-data">—</span>;
  const decimals = currency === 'PLN' ? 2 : 8;
  return `${parseFloat(val).toLocaleString('pl-PL', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })} ${currency}`;
 };

 return (
  <div className="operations-page">
   <div className="page-header">
    <h2>Operacje</h2>
    <div className="header-actions">
     <button className="btn-export" onClick={() => handleExport('xlsx')}>⬇ XLSX</button>
     <button className="btn-export" onClick={() => handleExport('csv')}>⬇ CSV</button>
    </div>
   </div>

   <div className="filters">
    <div className="filter-group">
     <label>Typ operacji:</label>
     <select name="operation_type" value={filters.operation_type} onChange={handleFilterChange}>
      <option value="">Wszystkie</option>
      {types.map(t => <option key={t} value={t}>{t}</option>)}
     </select>
    </div>
    <div className="filter-group">
     <label>Waluta:</label>
     <select name="currency" value={filters.currency} onChange={handleFilterChange}>
      <option value="">Wszystkie</option>
      {currencies.map(c => <option key={c} value={c}>{c}</option>)}
     </select>
    </div>
    <div className="filter-group">
     <label>Data od:</label>
     <input type="date" name="date_from" value={filters.date_from} onChange={handleFilterChange} />
    </div>
    <div className="filter-group">
     <label>Data do:</label>
     <input type="date" name="date_to" value={filters.date_to} onChange={handleFilterChange} />
    </div>
   </div>

   <div className="table-container">
    <table className="operations-table">
     <thead>
      <tr>
       {[
        { label: 'Data',            col: 'datetime' },
        { label: 'Typ operacji',    col: 'operation_type' },
        { label: 'Kwota',           col: 'amount',        cls: 'number' },
        { label: 'Saldo dostępne',  col: null,            cls: 'number' },
        { label: 'Saldo całkowite', col: 'balance_total', cls: 'number' },
        { label: 'Transakcja',      col: null },
        { label: '',                col: null,            cls: 'col-verify' },
       ].map(({ label, col, cls }) => (
        <th key={label}
         className={[cls, col ? 'sortable' : ''].filter(Boolean).join(' ')}
         onClick={col ? () => handleSort(col) : undefined}
        >
         {label}{col ? sortArrow(col) : ''}
        </th>
       ))}
      </tr>
     </thead>
     <tbody>
      {loading
       ? <tr><td colSpan={7} className="loading">Ładowanie...</td></tr>
       : operations.length === 0
        ? <tr><td colSpan={7} className="no-data-row">Brak operacji</td></tr>
        : operations.map(op => (
         <tr key={op.id} className={`verify-${op.verification_status || 'unverified'}`}>
          <td className="datetime">{op.datetime}</td>
          <td className="op-type">{op.operation_type}</td>
          <td className="number">{formatAmount(op)}</td>
          <td className="number">{formatBalance(op.balance_available, op.currency)}</td>
          <td className="number">{formatBalance(op.balance_total, op.currency)}</td>
          <td className="tx-link">
           {op.transaction_id
            ? <span className="tx-badge" title={op.transaction_id}>✓ powiązana</span>
            : <span className="no-data">—</span>}
          </td>
          <td className="col-verify">
           <button
            className={`btn-verify status-${op.verification_status || 'unverified'}`}
            onClick={() => handleVerify(op)}
            title={VERIFY_TITLES[op.verification_status || 'unverified']}
           >
            {VERIFY_LABELS[op.verification_status || 'unverified']}
           </button>
          </td>
         </tr>
        ))
      }
     </tbody>
    </table>
   </div>

   {pagination.pages > 1 && (
    <div className="pagination">
     <button onClick={() => setPagination(p => ({ ...p, offset: 0 }))} disabled={pagination.offset === 0}>«</button>
     <button onClick={() => setPagination(p => ({ ...p, offset: Math.max(0, p.offset - p.limit) }))} disabled={pagination.offset === 0}>‹</button>
     <span>Strona {currentPage} z {pagination.pages} ({pagination.total} operacji)</span>
     <button onClick={() => setPagination(p => ({ ...p, offset: p.offset + p.limit }))} disabled={currentPage >= pagination.pages}>›</button>
     <button onClick={() => setPagination(p => ({ ...p, offset: (p.pages - 1) * p.limit }))} disabled={currentPage >= pagination.pages}>»</button>
    </div>
   )}
  </div>
 );
}

export default OperationsPage;
