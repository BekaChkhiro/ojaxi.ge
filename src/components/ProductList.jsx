import React, { useState, useEffect, useRef } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useCart } from '../context/CartContext';
import ChristmasGift from '../images/christmas_gift.webp';

const ProductGrid = () => {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const { addToCart } = useCart();
  const [isAdding, setIsAdding] = useState(null);
  const [visibleProducts, setVisibleProducts] = useState(window.innerWidth >= 1024 ? 9 : 6);
  const [hasMore, setHasMore] = useState(true);
  const gridRef = useRef(null);
  const location = useLocation();

  // სქროლის პოზიციის აღდგენა
  useEffect(() => {
    const savedScrollPosition = sessionStorage.getItem('productGridScroll');
    const savedVisibleProducts = sessionStorage.getItem('visibleProducts');
    
    if (savedVisibleProducts) {
      setVisibleProducts(parseInt(savedVisibleProducts));
    }
    
    if (savedScrollPosition && location.state?.fromProduct) {
      window.scrollTo(0, parseInt(savedScrollPosition));
      // წავშალოთ fromProduct ფლაგი
      window.history.replaceState({}, document.title);
    }
  }, [location]);

  // სქროლის პოზიციის შენახვა პროდუქტზე გადასვლისას
  const handleProductClick = () => {
    sessionStorage.setItem('productGridScroll', window.scrollY.toString());
    sessionStorage.setItem('visibleProducts', visibleProducts.toString());
  };

  useEffect(() => {
    const fetchProducts = async () => {
      try {
        setLoading(true);
        setError(null);

        const credentials = btoa('ck_7e0151067429b58a0301585b70f1fba23ae424a0:cs_b1088b2fe946ed8e5c85e7be12f1bbc62efc79ac');
        
        const domain = window.location.hostname;
        const protocol = window.location.protocol;
        const apiUrl = `${protocol}//${domain}/wp-json/wc/v3/products?per_page=50`;
        
        const response = await fetch(apiUrl, {
          method: 'GET',
          headers: {
            'Authorization': `Basic ${credentials}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
        });

        if (!response.ok) {
          const errorData = await response.json();
          throw new Error(errorData.message || 'პროდუქტების წამოღება ვერ მოხერხდა');
        }

        const data = await response.json();
        setProducts(data);
      } catch (error) {
        console.error('Error:', error);
        setError(error.message);
      } finally {
        setLoading(false);
      }
    };

    fetchProducts();
  }, []);

  const handleAddToCart = async (e, product) => {
    e.preventDefault();
    setIsAdding(product.id);
    await addToCart({
      id: product.id,
      name: product.name,
      price: parseFloat(product.price),
      image: product.images[0]?.src || '/wp-content/uploads/2024/11/placeholder-1.png',
    });
    
    setTimeout(() => {
      setIsAdding(null);
    }, 500);
  };

  const loadMore = () => {
    const increment = window.innerWidth >= 1024 ? 9 : 6;
    const nextVisible = visibleProducts + increment;
    setVisibleProducts(nextVisible);
    if (nextVisible >= products.length) {
      setHasMore(false);
    }
  };

  if (loading) {
    return (
      <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-6 px-3 md:px-0">
        {[...Array(window.innerWidth >= 1024 ? 9 : 6)].map((_, index) => (
          <div key={index} className="bg-white rounded-lg overflow-hidden shadow-sm animate-pulse">
            <div className="w-full aspect-square bg-gray-200" />
            <div className="p-3 md:p-4">
              <div className="h-3 md:h-4 bg-gray-200 rounded w-1/2 mb-2" />
              <div className="h-4 md:h-5 bg-gray-200 rounded mb-3" />
              <div className="h-3 md:h-4 bg-gray-200 rounded w-1/3" />
              <div className="h-8 md:h-10 bg-gray-200 rounded mt-3" />
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (error) {
    return (
      <div className="text-center py-4 md:py-6 px-3">
        <p className="text-red-500 text-sm md:text-base">{error}</p>
      </div>
    );
  }

  if (!products || products.length === 0) {
    return (
      <div className="text-center py-4 md:py-6 px-3">
        <p className="text-gray-500 text-sm md:text-base">პროდუქტები არ მოიძებნა</p>
      </div>
    );
  }

  const calculateDiscount = (regularPrice, salePrice) => {
    const regular = parseFloat(regularPrice);
    const sale = parseFloat(salePrice);
    if (!regular || !sale) return 0;
    return Math.round(((regular - sale) / regular) * 100);
  };

  const createSlug = (name) => {
    return name
      .replace(/\(|\)/g, '')
      .normalize('NFKD')
      .trim();
  };

  return (
    <div className="flex flex-col gap-3 md:gap-6 px-3 md:px-0 pb-16 md:pb-6" ref={gridRef}>
      <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-6">
        {products.slice(0, visibleProducts).map((product) => (
          <div key={product.id} className="bg-white rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow">
            <Link 
              to={`/product/${product?.slug || createSlug(product.name)}`} 
              state={{ productId: product.id }} 
              className="block relative"
              onClick={handleProductClick}
            >
              <div className="relative">
                <img
                  src={product.images[0]?.src || '/wp-content/uploads/2024/11/placeholder-1.png'}
                  alt={product.name}
                  className="w-full aspect-square object-cover bg-gray-100"
                  onError={(e) => {
                    e.target.src = '/wp-content/uploads/2024/11/placeholder-1.png';
                  }}
                />
                <img
                  src={ChristmasGift}
                  alt="Christmas Gift"
                  className="absolute -top-1 -right-1 w-10 h-10 md:w-16 md:h-16 transform rotate-12 z-10"
                />
                {product.on_sale && (
                  <div className="absolute top-2 left-2 bg-[#ad2421] text-white text-[10px] md:text-xs px-1.5 md:px-2 py-0.5 md:py-1 rounded">
                    ფასდაკლება {calculateDiscount(product.regular_price, product.price)}%
                  </div>
                )}
              </div>
              
              <div className="p-2.5 md:p-4">
                <div className="text-[10px] md:text-xs text-gray-500 mb-1">
                  {product.categories?.[0]?.name || 'Uncategorized'}
                </div>
                <h3 className="font-medium text-gray-900 mb-1.5 md:mb-2 text-xs md:text-base line-clamp-2 leading-tight md:leading-normal">
                  {product.name}
                </h3>
                
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-1.5 md:gap-2">
                    {product.regular_price !== product.price && (
                      <span className="text-gray-400 line-through text-[10px] md:text-sm">
                        {product.regular_price}₾
                      </span>
                    )}
                    <span className="font-medium text-gray-900 text-sm md:text-base">
                      {product.price}₾
                    </span>
                  </div>
                </div>
              </div>
            </Link>

            <div className="px-2.5 md:px-4 pb-2.5 md:pb-4">
              <button 
                onClick={(e) => handleAddToCart(e, product)}
                className={`w-full h-8 md:h-10 flex items-center justify-center gap-1.5 md:gap-4 bg-[#1a691a] rounded-full transition-colors ${
                  isAdding === product.id ? 'opacity-75' : ''
                }`}
                disabled={isAdding === product.id}
              >
                <span className='text-white text-xs md:text-base'>
                  {isAdding === product.id ? 'ემატება...' : 'დამატება'}
                </span>
                {isAdding !== product.id && (
                  <svg 
                    xmlns="http://www.w3.org/2000/svg" 
                    className="w-3.5 h-3.5 md:w-5 md:h-5 text-white"
                    fill="none"
                    viewBox="0 0 24 24" 
                    stroke="currentColor"
                  >
                    <path 
                      strokeLinecap="round" 
                      strokeLinejoin="round" 
                      strokeWidth={2} 
                      d="M12 4v16m8-8H4" 
                    />
                  </svg>
                )}
              </button>
            </div>
          </div>
        ))}
      </div>
      
      {hasMore && products.length > visibleProducts && (
        <div className="flex justify-center mt-4 md:mt-8">
          <button
            onClick={loadMore}
            className="bg-[#1a691a] text-white px-3 md:px-6 py-2 rounded-full hover:bg-[#145214] transition-colors text-xs md:text-base"
          >
            მეტის ჩატვირთვა
          </button>
        </div>
      )}
    </div>
  );
};

export default ProductGrid;