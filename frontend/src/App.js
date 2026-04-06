import React from 'react';
import { BrowserRouter as Router, Routes, Route, Link, useLocation } from 'react-router-dom';
import './App.css';
import ImportPage from './pages/ImportPage';
import TransactionsPage from './pages/TransactionsPage';
import DashboardPage from './pages/DashboardPage';
import StatisticsPage from './pages/StatisticsPage';
import ProfitAnalysisPage from './pages/ProfitAnalysisPage';

// Komponent nawigacji z aktywnym linkiem
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

function App() {
 return (
  <Router>
   <div className="App">
    <nav className="navbar">
     <div className="nav-container">
      <h1 className="nav-logo">💰 Crypto Tracker</h1>
      <ul className="nav-menu">
       <NavLink to="/">Dashboard</NavLink>
       <NavLink to="/import">Import</NavLink>
       <NavLink to="/transactions">Transakcje</NavLink>
       <NavLink to="/statistics">Statystyki</NavLink>
       <NavLink to="/profit-analysis">📈 Zyski/Straty</NavLink>
      </ul>
     </div>
    </nav>

    <div className="main-content">
     <Routes>
      <Route path="/" element={<DashboardPage />} />
      <Route path="/import" element={<ImportPage />} />
      <Route path="/transactions" element={<TransactionsPage />} />
      <Route path="/statistics" element={<StatisticsPage />} />
      <Route path="/profit-analysis" element={<ProfitAnalysisPage />} />
     </Routes>
    </div>
   </div>
  </Router>
 );
}

export default App;
