import React, { useState, useEffect } from 'react';
import { BrowserRouter as Router, Routes, Route, Link, useLocation, useNavigate } from 'react-router-dom';
import './App.css';
import ImportPage from './pages/ImportPage';
import TransactionsPage from './pages/TransactionsPage';
import DashboardPage from './pages/DashboardPage';
import StatisticsPage from './pages/StatisticsPage';
import ProfitAnalysisPage from './pages/ProfitAnalysisPage';
import SimpleProfitPage from './pages/SimpleProfitPage';
import OperationsPage from './pages/OperationsPage';
import { getMarkets } from './services/api';

function NavLink({ to, children }) {
  const location = useLocation();
  const isActive = location.pathname === to;
  return (
    <li className="nav-item">
      <Link to={to} className={`nav-link ${isActive ? 'active' : ''}`}>
        {children}
      </Link>
    </li>
  );
}

function Sidebar({ selectedMarket, onSelectMarket }) {
  const [markets, setMarkets] = useState([]);
  const location = useLocation();
  const navigate = useNavigate();

  useEffect(() => {
    getMarkets()
      .then(data => setMarkets(data.markets || []))
      .catch(() => setMarkets([]));
  }, []);

  const getCryptoSymbol = (market) => market.split('-')[0];

  const handleSelect = (market) => {
    onSelectMarket(market);
    if (location.pathname !== '/transactions') {
      navigate('/transactions');
    }
  };

  return (
    <aside className="sidebar">
      <div className="sidebar-header">Filtr transakcji</div>
      <ul className="sidebar-list">
        <li
          className={`sidebar-item ${selectedMarket === null ? 'active' : ''}`}
          onClick={() => handleSelect(null)}
        >
          <span className="sidebar-icon">🌐</span>
          Wszystkie
        </li>
        {markets.map(market => (
          <li
            key={market}
            className={`sidebar-item ${selectedMarket === market ? 'active' : ''}`}
            onClick={() => handleSelect(market)}
          >
            <span className="sidebar-icon">₿</span>
            {getCryptoSymbol(market)}
            <span className="sidebar-market">{market}</span>
          </li>
        ))}
      </ul>
    </aside>
  );
}

function AppLayout() {
  const [selectedMarket, setSelectedMarket] = useState(null);

  return (
    <div className="App">
      <nav className="navbar">
        <div className="nav-container">
          <h1 className="nav-logo">Crypto Tracker</h1>
          <ul className="nav-menu">
            <NavLink to="/">Dashboard</NavLink>
            <NavLink to="/import">Import</NavLink>
            <NavLink to="/transactions">Transakcje</NavLink>
            <NavLink to="/operations">Operacje</NavLink>
            <NavLink to="/statistics">Statystyki</NavLink>
            <NavLink to="/profit-analysis">Zyski / Straty FIFO</NavLink>
            <NavLink to="/simple-profit">Zyski / Straty</NavLink>
          </ul>
        </div>
      </nav>

      <div className="app-body">
        <Sidebar selectedMarket={selectedMarket} onSelectMarket={setSelectedMarket} />
        <main className="main-content">
          <Routes>
            <Route path="/" element={<DashboardPage />} />
            <Route path="/import" element={<ImportPage />} />
            <Route path="/transactions" element={<TransactionsPage selectedMarket={selectedMarket} />} />
            <Route path="/statistics" element={<StatisticsPage />} />
            <Route path="/profit-analysis" element={<ProfitAnalysisPage />} />
            <Route path="/simple-profit" element={<SimpleProfitPage />} />
            <Route path="/operations" element={<OperationsPage />} />
          </Routes>
        </main>
      </div>
    </div>
  );
}

function App() {
  return (
    <Router>
      <AppLayout />
    </Router>
  );
}

export default App;
