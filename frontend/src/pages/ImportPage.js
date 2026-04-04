import React, { useState } from 'react';
import api from '../services/api';
import './ImportPage.css';

function ImportPage() {
  const [files, setFiles] = useState([]);
  const [detectedFiles, setDetectedFiles] = useState([]);
  const [importing, setImporting] = useState(false);
  const [importResults, setImportResults] = useState([]);
  const [dragActive, setDragActive] = useState(false);

  const handleDrag = (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (e.type === "dragenter" || e.type === "dragover") {
      setDragActive(true);
    } else if (e.type === "dragleave") {
      setDragActive(false);
    }
  };

  const handleDrop = (e) => {
    e.preventDefault();
    e.stopPropagation();
    setDragActive(false);
    
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      handleFiles(e.dataTransfer.files);
    }
  };

  const handleChange = (e) => {
    e.preventDefault();
    if (e.target.files && e.target.files.length > 0) {
      handleFiles(e.target.files);
    }
  };

  const handleFiles = async (fileList) => {
    const newFiles = Array.from(fileList);
    setFiles([...files, ...newFiles]);
    
    // Wykryj typ każdego pliku
    const detected = [];
    for (const file of newFiles) {
      try {
        const result = await api.detectFile(file);
        if (result.success) {
          detected.push({
            file,
            info: result.file_info
          });
        }
      } catch (error) {
        console.error('Błąd wykrywania pliku:', error);
        detected.push({
          file,
          info: null,
          error: error.message
        });
      }
    }
    
    setDetectedFiles([...detectedFiles, ...detected]);
  };

  const removeFile = (index) => {
    const newFiles = [...files];
    const newDetected = [...detectedFiles];
    newFiles.splice(index, 1);
    newDetected.splice(index, 1);
    setFiles(newFiles);
    setDetectedFiles(newDetected);
  };

  const handleImport = async () => {
    if (files.length === 0) return;
    
    setImporting(true);
    setImportResults([]);
    
    const results = [];
    
    for (let i = 0; i < files.length; i++) {
      try {
        const result = await api.importFile(files[i]);
        results.push({
          fileName: files[i].name,
          success: result.success,
          imported: result.imported,
          total: result.total,
          errors: result.errors
        });
      } catch (error) {
        results.push({
          fileName: files[i].name,
          success: false,
          error: error.message
        });
      }
    }
    
    setImportResults(results);
    setImporting(false);
  };

  const getTypeIcon = (type) => {
    const icons = {
      'transactions': '📊',
      'detailed_report': '📋',
      'operations': '📝',
      'transfers': '💸'
    };
    return icons[type] || '📄';
  };

  const getTypeColor = (type) => {
    const colors = {
      'transactions': '#4caf50',
      'detailed_report': '#2196f3',
      'operations': '#ff9800',
      'transfers': '#9c27b0'
    };
    return colors[type] || '#666';
  };

  return (
    <div className="import-page">
      <h2>Import danych z Zonda/BitBay</h2>
      
      <div className="import-section">
        <div 
          className={`dropzone ${dragActive ? 'active' : ''}`}
          onDragEnter={handleDrag}
          onDragLeave={handleDrag}
          onDragOver={handleDrag}
          onDrop={handleDrop}
        >
          <input
            type="file"
            id="file-input"
            multiple
            accept=".csv"
            onChange={handleChange}
            style={{ display: 'none' }}
          />
          <label htmlFor="file-input" className="file-label">
            <div className="dropzone-content">
              <div className="upload-icon">📁</div>
              <p className="dropzone-text">
                Przeciągnij pliki CSV tutaj<br />
                lub kliknij aby wybrać
              </p>
              <p className="dropzone-hint">
                Możesz wrzucić 1-4 pliki naraz
              </p>
            </div>
          </label>
        </div>

        {detectedFiles.length > 0 && (
          <div className="files-detected">
            <h3>Wykryte pliki:</h3>
            <div className="files-list">
              {detectedFiles.map((item, index) => (
                <div key={index} className="file-item">
                  <div className="file-info">
                    <span 
                      className="file-icon"
                      style={{ color: getTypeColor(item.info?.type) }}
                    >
                      {getTypeIcon(item.info?.type)}
                    </span>
                    <div className="file-details">
                      <div className="file-name">{item.file.name}</div>
                      {item.info && (
                        <div className="file-meta">
                          <span className="file-type">{item.info.type_label}</span>
                          <span className="file-records">{item.info.records_count} rekordów</span>
                          {item.info.date_from && (
                            <span className="file-date">
                              {item.info.date_from} - {item.info.date_to}
                            </span>
                          )}
                        </div>
                      )}
                      {item.error && (
                        <div className="file-error">❌ {item.error}</div>
                      )}
                    </div>
                  </div>
                  <button 
                    className="remove-btn"
                    onClick={() => removeFile(index)}
                  >
                    ✕
                  </button>
                </div>
              ))}
            </div>

            <div className="import-actions">
              <button 
                className="btn-import"
                onClick={handleImport}
                disabled={importing || files.length === 0}
              >
                {importing ? '⏳ Importuję...' : '✅ Importuj do bazy'}
              </button>
              <button 
                className="btn-cancel"
                onClick={() => {
                  setFiles([]);
                  setDetectedFiles([]);
                  setImportResults([]);
                }}
                disabled={importing}
              >
                Anuluj
              </button>
            </div>
          </div>
        )}

        {importResults.length > 0 && (
          <div className="import-results">
            <h3>Wyniki importu:</h3>
            {importResults.map((result, index) => (
              <div 
                key={index} 
                className={`result-item ${result.success ? 'success' : 'error'}`}
              >
                <div className="result-icon">
                  {result.success ? '✅' : '❌'}
                </div>
                <div className="result-info">
                  <div className="result-file">{result.fileName}</div>
                  {result.success ? (
                    <div className="result-stats">
                      Zaimportowano: {result.imported} / {result.total} rekordów
                      {result.errors && result.errors.length > 0 && (
                        <div className="result-errors">
                          Błędy: {result.errors.length}
                        </div>
                      )}
                    </div>
                  ) : (
                    <div className="result-error">{result.error}</div>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

export default ImportPage;
