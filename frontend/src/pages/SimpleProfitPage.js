import React, { useState, useEffect } from 'react';
import { getSimpleProfit } from '../services/api';
import './SimpleProfitPage.css';

const fmt2 = v => parseFloat(v).toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const sign = v => v >= 0 ? '+' : '';

function SimpleProfitPage() {
 const [data, setData]         = useState(null);
 const [loading, setLoading]   = useState(true);
 const [expanded, setExpanded] = useState({});

 useEffect(() => {
  getSimpleProfit()
   .then(d => setData(d))
   .catch(console.error)
   .finally(() => setLoading(false));
 }, []);

 const toggle = (year) => setExpanded(prev => ({ ...prev, [year]: !prev[year] }));

 if (loading) return <div className="sp-page"><p className="sp-loading">Ładowanie...</p></div>;
 if (!data?.years?.length) return <div className="sp-page"><p className="sp-loading">Brak danych</p></div>;

 return (
  <div className="sp-page">
   <div className="sp-header">
    <h2>Zyski / Straty — metoda uproszczona</h2>
    <p className="sp-subtitle">
     Wynik = Przychody ze sprzedaży − Koszty zakupu − Prowizje · liczone per rok, bez FIFO
    </p>
   </div>

   <div className="sp-table-wrap">
    <table className="sp-table">
     <thead>
      <tr>
       <th>Rok</th>
       <th className="num">Przychody</th>
       <th className="num">Koszty zakupu</th>
       <th className="num">Prowizje</th>
       <th className="num">Wynik roku</th>
       <th className="num">Wynik skumulowany</th>
      </tr>
     </thead>
     <tbody>
      {data.years.map(row => {
       const profitCls = row.profit >= 0 ? 'pos' : 'neg';
       const cumCls    = row.cumulative >= 0 ? 'pos' : 'neg';
       const isOpen    = expanded[row.year];
       return (
        <React.Fragment key={row.year}>
         <tr
          className={`sp-year-row ${isOpen ? 'open' : ''}`}
          onClick={() => toggle(row.year)}
          title="Kliknij aby zobaczyć podział per kryptowaluta"
         >
          <td className="sp-year-cell">
           <span className="sp-toggle">{isOpen ? '▾' : '▸'}</span>
           {row.year}
          </td>
          <td className="num">{fmt2(row.sell_revenue)} PLN</td>
          <td className="num">{fmt2(row.buy_cost)} PLN</td>
          <td className="num">{fmt2(row.fees)} PLN</td>
          <td className={`num sp-profit ${profitCls}`}>
           {sign(row.profit)}{fmt2(row.profit)} PLN
          </td>
          <td className={`num sp-cumulative ${cumCls}`}>
           {sign(row.cumulative)}{fmt2(row.cumulative)} PLN
          </td>
         </tr>

         {isOpen && row.cryptos.map(c => {
          const cCls = c.profit >= 0 ? 'pos' : 'neg';
          return (
           <tr key={c.crypto} className="sp-crypto-row">
            <td className="sp-crypto-name">{c.crypto}</td>
            <td className="num">{fmt2(c.sell_revenue)} PLN</td>
            <td className="num">{fmt2(c.buy_cost)} PLN</td>
            <td className="num">{fmt2(c.fees)} PLN</td>
            <td className={`num sp-profit ${cCls}`}>
             {sign(c.profit)}{fmt2(c.profit)} PLN
            </td>
            <td className="num sp-cumulative-empty">—</td>
           </tr>
          );
         })}
        </React.Fragment>
       );
      })}
     </tbody>
     <tfoot>
      {(() => {
       const total = data.years.reduce((acc, r) => ({
        sell:  acc.sell  + r.sell_revenue,
        cost:  acc.cost  + r.buy_cost,
        fees:  acc.fees  + r.fees,
        profit:acc.profit+ r.profit,
       }), { sell: 0, cost: 0, fees: 0, profit: 0 });
       const cls = total.profit >= 0 ? 'pos' : 'neg';
       return (
        <tr className="sp-total-row">
         <td>Łącznie</td>
         <td className="num">{fmt2(total.sell)} PLN</td>
         <td className="num">{fmt2(total.cost)} PLN</td>
         <td className="num">{fmt2(total.fees)} PLN</td>
         <td className={`num sp-profit ${cls}`}>{sign(total.profit)}{fmt2(total.profit)} PLN</td>
         <td className="num"></td>
        </tr>
       );
      })()}
     </tfoot>
    </table>
   </div>

   <div className="sp-note">
    <strong>Metoda uproszczona:</strong> wynik = przychody ze sprzedaży − koszty zakupu − prowizje PLN,
    wszystko liczone w roku kalendarzowym. Prowizje krypto (np. LTC pobrane przy kupnie) nie są tu uwzględnione,
    bo nie mają bezpośredniej wartości PLN. Nie uwzględnia przenoszenia straty z poprzednich lat (to robi Twój księgowy przy PIT-38).
   </div>
  </div>
 );
}

export default SimpleProfitPage;
