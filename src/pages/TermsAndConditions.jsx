import React from 'react';

const TermsAndConditionsViewer = () => {
  return (
    <div style={{ padding: '20px', textAlign: 'center' }}>
      <h2 className='mb-4'>წესები და პირობები</h2>
      <iframe
        src="https://docs.google.com/document/d/1CQgLmLfcv-UHPmP5QShLiMkDF5XbTLSS/preview"
        width="100%"
        height="800px"
        allow="autoplay"
        style={{
          border: '1px solid #ddd',
          borderRadius: '8px',
        }}
        title="Terms and Conditions Document"
      ></iframe>
    </div>
  );
};

export default TermsAndConditionsViewer;
