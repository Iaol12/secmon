import React from 'react';
import './DataTable.css';

const DataTable = ({ html, pagination, onPageChange }) => {
  const handlePageClick = (page) => {
    if (onPageChange) {
      onPageChange(page);
    }
  };

  return (
    <div className="data-table-container">
      <div 
        className="table-content"
        dangerouslySetInnerHTML={{ __html: html }}
      />
      {pagination && (
        <div className="table-pagination">
          {pagination}
        </div>
      )}
    </div>
  );
};

export default DataTable;
