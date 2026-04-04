import React, { useState, useEffect } from 'react';
import api from '../services/api';
import './TransactionsPage.css';

function TransactionsPage() {
  const [transactions, setTransactions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [pagination, setPagination] = useState({
    total: 0,
    limit: 50,
    offset: 0,
    pages: 0
  });
  const [filters, setFilters] = useState({
    market: '',
    type: '',
    date_from: '',
    date_to: ''
  });

  useEffect(() => {
    loadTransactions();
  }, [pagination.offset, filters]);

  const loadTransactions = async () => {
    setLoading(true);
    try {
      const params = {
        limit: pagination.limit,
        offset: pagination.offset,
        ...filters
      };
      
      // Usuń puste filtry
      Object.keys(params).forEach(key => {
        if (params[key] === '') delete params[key];
      });

      const response = await api.getTransactions(params);
      
      if (response.success) {
        setTransactions(response.data);
        setPagination(prev => ({
          ...prev,
          ...response.pagination
        }));
      }
    } catch (error) {
      console.error('Błąd ładowania transakcji:', error);
    }
    setLoading(false);
  };

  const handleFilterChange = (e) => {
    const { name, value } = e.target;
    setFilters(prev => ({
      ...prev,
      [name]: value
    }));
    setPagination(prev => ({ ...prev, offset: 0 }));
  };

  const handlePageChange = (newPage) => {
    setPagination(prev => ({
      ...prev,
      offset: (newPage - 1) * prev.limit
    }));
  };

  const formatNumber = (num) => {
    return parseFloat(num).toLocaleString('pl-PL', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 8
    });
  };

  const formatDate = (dateStr) => {
    const date = new Date(dateStr);
    return date.toLocaleString('pl-PL');
  };

  const currentPage = Math.floor(pagination.offset / pagination.limit) + 1;

  return (
    <div className="transactions-page">
      <h2>Historia transakcji</h2>

      <div className="filters">
        <div className="filter-group">
          <label>Rynek:</label>
          <select 
            name="market" 
            value={filters.market} 
            onChange={handleFilterChange}
          >
            <option value="">Wszystkie</option>
            <option value="BCC-PLN">BCC-PLN</option>
            <option value="BTC-PLN">BTC-PLN</option>
            <option value="ETH-PLN">ETH-PLN</option>
            <option value="LTC-PLN">LTC-PLN</option>
            <option value="LSK-PLN">LSK-PLN</option>
            <option value="DASH-PLN">DASH-PLN</option>
          </select>
        </div>

        <div className="filter-group">
          <label>Typ:</label>
          <select 
            name="type" 
            value={filters.type} 
            onChange={handleFilterChange}
          >
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

      {loading ? (
        <div className="loading">Ładowanie...</div>
      ) : (
        <>
          <div className="table-container">
            <table className="transactions-table">
              <thead>
                <tr>
                  <th>Data</th>
                  <th>Rynek</th>
                  <th>Typ</th>
                  <th>Zlecenie</th>
                  <th>Kurs</th>
                  <th>Ilość</th>
                  <th>Wartość</th>
                </tr>
              </thead>
              <tbody>
                {transactions.map((tx) => (
                  <tr key={tx.id}>
                    <td>{formatDate(tx.datetime)}</td>
                    <td><span className="market-badge">{tx.market}</span></td>
                    <td>
                      <span className={`type-badge ${tx.type}`}>
                        {tx.type === 'buy' ? '🟢 Kupno' : '🔴 Sprzedaż'}
                      </span>
                    </td>
                    <td>
                      <span className={`order-badge ${tx.order_type}`}>
                        {tx.order_type}
                      </span>
                    </td>
                    <td className="number">{formatNumber(tx.rate)} PLN</td>
                    <td className="number">{formatNumber(tx.amount)}</td>
                    <td className="number value">{formatNumber(tx.value)} PLN</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="pagination">
            <div className="pagination-info">
              Wyświetlam {pagination.offset + 1}-{Math.min(pagination.offset + pagination.limit, pagination.total)} z {pagination.total}
            </div>
            <div className="pagination-buttons">
              <button 
                onClick={() => handlePageChange(currentPage - 1)}
                disabled={currentPage === 1}
              >
                ← Poprzednia
              </button>
              <span className="page-info">
                Strona {currentPage} z {pagination.pages}
              </span>
              <button 
                onClick={() => handlePageChange(currentPage + 1)}
                disabled={currentPage === pagination.pages}
              >
                Następna →
              </button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}

export default TransactionsPage;
