import axios from 'axios';

const API_BASE_URL = 'http://localhost/Projekty-Github/crypto-tracker/backend/api';

const api = {
 /**
  * Wykrywa typ pliku CSV
  */
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

 /**
  * Importuje plik CSV do bazy
  */
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

 /**
  * Pobiera listę transakcji
  */
 getTransactions: async (params = {}) => {
  const response = await axios.get(`${API_BASE_URL}/transactions.php`, {
   params,
  });

  return response.data;
 },
};

export default api;
