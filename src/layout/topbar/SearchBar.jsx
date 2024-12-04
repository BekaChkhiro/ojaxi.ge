import React, { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';

const SearchBar = () => {
  const [searchTerm, setSearchTerm] = useState('');
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [isResultsVisible, setIsResultsVisible] = useState(false);
  const [isMobile, setIsMobile] = useState(window.innerWidth < 768);
  const searchContainerRef = useRef(null);
  const mobileInputRef = useRef(null);
  const navigate = useNavigate();

  useEffect(() => {
    const handleResize = () => {
      setIsMobile(window.innerWidth < 768);
    };

    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  useEffect(() => {
    const delayDebounceFn = setTimeout(() => {
      if (searchTerm) {
        searchProducts();
      } else {
        setProducts([]);
      }
    }, isMobile ? 1000 : 500);

    return () => clearTimeout(delayDebounceFn);
  }, [searchTerm, isMobile]);

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (
        searchContainerRef.current && 
        !searchContainerRef.current.contains(event.target)
      ) {
        setIsResultsVisible(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  useEffect(() => {
    // Focus mobile input when results become visible
    if (isResultsVisible && isMobile && mobileInputRef.current) {
      mobileInputRef.current.focus();
    }
  }, [isResultsVisible, isMobile]);

  const searchProducts = async () => {
    setLoading(true);
    try {
      const credentials = btoa('ck_7e0151067429b58a0301585b70f1fba23ae424a0:cs_b1088b2fe946ed8e5c85e7be12f1bbc62efc79ac');
      
      const domain = window.location.hostname;
      const protocol = window.location.protocol;
      const apiUrl = `${protocol}//${domain}/wp-json/wc/v3/products?search=${encodeURIComponent(searchTerm)}&per_page=10`;
      
      const response = await fetch(apiUrl, {
        method: 'GET',
        headers: {
          'Authorization': `Basic ${credentials}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      });

      if (!response.ok) {
        throw new Error('პროდუქტების ძიება ვერ მოხერხდა');
      }

      const data = await response.json();
      setProducts(data);
      setError(null);
    } catch (error) {
      setError('პროდუქტების ძიება ვერ მოხერხდა');
    } finally {
      setLoading(false);
    }
  };

  const createSlug = (name) => {
    return name
      .replace(/\(|\)/g, '')
      .normalize('NFKD')
      .trim();
  };

  const handleProductClick = (product) => {
    setIsResultsVisible(false);
    setSearchTerm('');
    navigate(`/product/${product?.slug || createSlug(product.name)}`, { 
      state: { productId: product.id } 
    });
  };

  const handleMainInputFocus = () => {
    if (isMobile) {
      // On mobile, show the results container immediately when main input is focused
      setIsResultsVisible(true);
    } else if (searchTerm) {
      // On desktop, show results only if there's a search term
      setIsResultsVisible(true);
    }
  };

  const handleSearchInput = (e) => {
    const value = e.target.value;
    setSearchTerm(value);
    // Always show results container when typing
    if (value || isMobile) {
      setIsResultsVisible(true);
    } else {
      setIsResultsVisible(false);
    }
  };

  const handleClose = () => {
    setIsResultsVisible(false);
    setSearchTerm('');
  };

  const getStockStatus = (product) => {
    if (product.stock_status === 'instock') {
      return (
        <span className="text-green-600 text-xs font-medium">
          მარაგშია
        </span>
      );
    } else if (product.stock_status === 'outofstock') {
      return (
        <span className="text-red-600 text-xs font-medium">
          არ არის მარაგში
        </span>
      );
    } else {
      return (
        <span className="text-orange-600 text-xs font-medium">
          წინასწარი შეკვეთით
        </span>
      );
    }
  };

  return (
    <div className="relative w-full" ref={searchContainerRef}>
      {/* Desktop/Main Input */}
      <div className="w-full relative">
        <input 
          type="text"
          value={searchTerm}
          onChange={handleSearchInput}
          onFocus={handleMainInputFocus}
          placeholder="ძიება..."
          className="w-full px-4 py-2 md:py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent pl-10 md:pl-12 text-sm md:text-base"
        />
        <svg
          className="w-5 h-5 md:w-6 md:h-6 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth="2"
            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
          />
        </svg>
      </div>

      {isResultsVisible && (
        <div className="fixed md:absolute inset-0 md:inset-auto md:left-0 md:right-0 md:top-full mt-0 md:mt-1 bg-white md:rounded-lg shadow-lg z-50 md:max-h-[400px] h-full md:h-auto flex flex-col md:block">
          {/* Mobile Header with Search - Now sticky */}
          <div className="md:hidden sticky top-0 bg-white z-10 border-b border-gray-200">
            <div className="p-4">
              <div className="flex items-center justify-between">
                <div className="flex-1 flex items-center pr-2">
                  <div className="relative flex-1">
                    <input
                      ref={mobileInputRef}
                      type="text"
                      value={searchTerm}
                      onChange={handleSearchInput}
                      placeholder="ძიება..."
                      className="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent pl-10 pr-4 text-base"
                    />
                    <svg
                      className="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                      />
                    </svg>
                  </div>
                  <button 
                    onClick={handleClose}
                    className="ml-4 text-gray-500 hover:text-gray-700"
                  >
                    გაუქმება
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Scrollable Content Area */}
          <div className="flex-1 overflow-y-auto md:max-h-[400px]">
            {error && (
              <div className="p-4 text-red-500 text-center text-sm md:text-base">
                {error}
              </div>
            )}

            {loading ? (
              [...Array(3)].map((_, index) => (
                <div key={index} className="flex items-center gap-3 p-3 border-b border-gray-100 animate-pulse">
                  <div className="w-16 md:w-12 h-16 md:h-12 bg-gray-200 rounded-md"></div>
                  <div className="flex-1">
                    <div className="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                    <div className="h-3 bg-gray-200 rounded w-1/4"></div>
                  </div>
                </div>
              ))
            ) : (
              <div className="md:grid md:grid-rows-[repeat(5,auto)] md:overflow-y-auto">
                {products.map((product) => (
                  <div 
                    key={product.id} 
                    onClick={() => handleProductClick(product)}
                    className="flex items-center gap-3 p-4 md:p-3 border-b border-gray-100 hover:bg-gray-50 transition-colors cursor-pointer"
                  >
                    <div className="w-16 h-16 md:w-12 md:h-12 flex-shrink-0">
                      <img
                        src={product.images[0]?.src || '/wp-content/uploads/2024/11/placeholder-1.png'}
                        alt={product.name}
                        className="w-full h-full object-cover rounded-md"
                        onError={(e) => {
                          e.target.src = '/wp-content/uploads/2024/11/placeholder-1.png';
                        }}
                      />
                    </div>
                    <div className="flex-1 min-w-0">
                      <h3 className="font-medium text-gray-900 text-base md:text-sm truncate">
                        {product.name}
                      </h3>
                      <div className="flex items-center justify-between mt-2 md:mt-1">
                        <span className="text-base md:text-sm font-medium text-gray-900">
                          {product.price}₾
                        </span>
                        {getStockStatus(product)}
                      </div>
                    </div>
                  </div>
                ))}

                {products.length === 0 && searchTerm && !loading && (
                  <div className="p-4 text-center text-gray-500 text-sm md:text-base">
                    შედეგები ვერ მოიძებნა
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default SearchBar;