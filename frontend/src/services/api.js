import axios from 'axios';

const API_BASE_URL = 'http://localhost/Projekty-Github/crypto-tracker/backend/api';

// Wykrywanie typu pliku
export const detectFile = async (file) => {
 const formData = new FormData();
 formData.append('file', file);

 const response = await axios.post(`${API_BASE_URL}/detect.php`, formData, {
  headers: {
   'Content-Type': 'multipart/form-data',
  },
 });

 return response.data;
};

// Import pliku
export const importFile = async (file) => {
 const formData = new FormData();
 formData.append('file', file);

 const response = await axios.post(`${API_BASE_URL}/import.php`, formData, {
  headers: {
   'Content-Type': 'multipart/form-data',
  },
 });

 return response.data;
};

// Pobieranie transakcji
export const getTransactions = async (params = {}) => {
 const queryString = new URLSearchParams(params).toString();
 const response = await axios.get(`${API_BASE_URL}/transactions.php?${queryString}`);
 return response.data;
};

// Dodawanie transakcji
export const createTransaction = async (data) => {
 const response = await axios.post(`${API_BASE_URL}/transactions/create.php`, data);
 return response.data;
};

// Edycja transakcji
export const updateTransaction = async (id, data) => {
 const response = await axios.put(`${API_BASE_URL}/transactions/update.php`, {
  id,
  ...data
 });
 return response.data;
};

// Usuwanie transakcji
export const deleteTransaction = async (id) => {
 const response = await axios.delete(`${API_BASE_URL}/transactions/delete.php`, {
  data: { id }
 });
 return response.data;
};

// Pobieranie statystyk
export const getStatistics = async (year) => {
 const response = await axios.get(`${API_BASE_URL}/statistics.php?year=${year}`);
 return response.data;
};

// Analiza zysków/strat FIFO
export const getProfitAnalysis = async (year = null, crypto = null) => {
 let url = `${API_BASE_URL}/profit-analysis.php`;
 const params = new URLSearchParams();

 if (year && year !== 'all') {
  params.append('year', year);
 }
 if (crypto) {
  params.append('crypto', crypto);
 }

 if (params.toString()) {
  url += '?' + params.toString();
 }

 const response = await axios.get(url);
 return response.data;
};

// Domyślny eksport dla kompatybilności wstecznej
const api = {
 detectFile,
 importFile,
 getTransactions,
 createTransaction,
 updateTransaction,
 deleteTransaction,
 getStatistics,
 getProfitAnalysis,
};

export default api;
