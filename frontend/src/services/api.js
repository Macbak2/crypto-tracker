import axios from 'axios';

const API_BASE_URL = 'http://localhost/Projekty-Github/crypto-tracker/backend/api';

const api = {
 // Wykrywanie typu pliku
 detectFile: async (file) => {
  const formData = new FormData();
  formData.append('file', file);

  const response = await axios.post(`${API_BASE_URL}/detect.php`, formData, {
   headers: {
    'Content-Type': 'multipart/form-data',
   },
  });

  return response.data;
 },

 // Import pliku
 importFile: async (file) => {
  const formData = new FormData();
  formData.append('file', file);

  const response = await axios.post(`${API_BASE_URL}/import.php`, formData, {
   headers: {
    'Content-Type': 'multipart/form-data',
   },
  });

  return response.data;
 },

 // Pobieranie transakcji
 getTransactions: async (params = {}) => {
  const queryString = new URLSearchParams(params).toString();
  const response = await axios.get(`${API_BASE_URL}/transactions.php?${queryString}`);
  return response.data;
 },

 // Dodawanie transakcji
 createTransaction: async (data) => {
  const response = await axios.post(`${API_BASE_URL}/transactions/create.php`, data);
  return response.data;
 },

 // Edycja transakcji
 updateTransaction: async (id, data) => {
  const response = await axios.put(`${API_BASE_URL}/transactions/update.php`, {
   id,
   ...data
  });
  return response.data;
 },

 // Usuwanie transakcji
 deleteTransaction: async (id) => {
  const response = await axios.delete(`${API_BASE_URL}/transactions/delete.php`, {
   data: { id }
  });
  return response.data;
 },
};

export default api;