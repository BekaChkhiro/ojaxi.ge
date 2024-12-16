import React, { useState, useEffect } from 'react';
import { useParams, useLocation, Link, useNavigate } from 'react-router-dom';
import { useCart } from '../context/CartContext';
import Confetti from 'react-confetti';

const SingleProduct = () => {
  const { name } = useParams();
  const location = useLocation();
  const navigate = useNavigate();

  const getProductId = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const idFromUrl = urlParams.get('id');

    if (location.state?.productId) return location.state.productId;
    if (window.initialProductData?.productId) return window.initialProductData.productId;
    if (idFromUrl) return idFromUrl;

    return null;
  };

  const productId = getProductId();

  const [product, setProduct] = useState(null);
  const [relatedProducts, setRelatedProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const { addToCart, setShowRightSidebar } = useCart();
  const [isAdding, setIsAdding] = useState(false);
  const [activeImage, setActiveImage] = useState(0);
  const [showConfetti, setShowConfetti] = useState(false);

  const createSlug = (name) => {
    return name
      .replace(/\(|\)/g, '')
      .normalize('NFKD')
      .trim();
  };

  useEffect(() => {
    const fetchProduct = async () => {
      try {
        setLoading(true);
        setError(null);

        const currentProductId = getProductId();
        if (!currentProductId) {
          throw new Error('Product ID not found');
        }

        const response = await fetch(`${window.location.origin}/wp-json/wc/v3/products/${currentProductId}`, {
          credentials: 'include',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          }
        });
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }

        const data = await response.json();
        if (!data || !data.id) {
          throw new Error('Invalid product data');
        }

        setProduct(data);

        const baseTitle = document.title.split(' - ')[1] || '';
        document.title = data.name + (baseTitle ? ` - ${baseTitle}` : '');

        const correctSlug = data?.slug || createSlug(data.name);
        if (decodeURIComponent(name) !== correctSlug) {
          navigate(`/product/${correctSlug}?id=${data.id}`, {
            state: { productId: data.id },
            replace: true
          });
        }

        if (data.categories?.[0]?.id) {
          const relatedResponse = await fetch(
            `${window.location.origin}/wp-json/wc/v3/products?category=${data.categories[0].id}&exclude=${currentProductId}&per_page=4`, {
              credentials: 'include',
              headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
              }
            }
          );
          if (relatedResponse.ok) {
            const relatedData = await relatedResponse.json();
            setRelatedProducts(relatedData);
          }
        }

      } catch (error) {
        console.error("Error fetching product:", error);
        let errorMessage = 'დაფიქსირდა შეცდომა';

        if (!navigator.onLine) {
          errorMessage = 'ინტერნეტთან კავშირი ვერ მოხერხდა';
        } else if (error.message.includes('Product ID not found')) {
          errorMessage = 'პროდუქტის ID ვერ მოიძებნა';
        } else if (error.message.includes('Invalid product data')) {
          errorMessage = 'პროდუქტის მონაცემები არასწორია';
        } else if (error.message.includes('Network response was not ok')) {
          errorMessage = 'სერვერთან კავშირი ვერ მოხერხდა';
        }

        setError(errorMessage);
      } finally {
        setLoading(false);
      }
    };

    fetchProduct();
  }, [productId, name, navigate]);

  const handleAddToCart = async () => {
    setIsAdding(true);
    try {
      await addToCart({
        id: product.id,
        name: product.name,
        price: parseFloat(product.price),
        image: product.images[0]?.src || '/wp-content/uploads/2024/11/placeholder-1.png',
        quantity: 1,
        stock_status: product.stock_status,
        regular_price: parseFloat(product.regular_price || product.price),
        sale_price: product.sale_price ? parseFloat(product.sale_price) : null,
      });

      setShowConfetti(true);
      setTimeout(() => setShowConfetti(false), 3000);

      setShowRightSidebar(true);

      if (window.innerWidth < 1024) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    } catch (error) {
      console.error("Error adding to cart:", error);
      alert('პროდუქტის დამატება ვერ მოხერხდა. გთხოვთ სცადოთ თავიდან.');
    } finally {
      setIsAdding(false);
    }
  };

  const handleBackToProducts = () => {
    if (window.history.length > 2) {
      navigate(-1);
    } else {
      navigate('/');
    }
  };

  const calculateDiscount = (regularPrice, salePrice) => {
    const regular = parseFloat(regularPrice);
    const sale = parseFloat(salePrice);
    if (!regular || !sale) return 0;
    return Math.round(((regular - sale) / regular) * 100);
  };

  const handleCheckout = async () => {
    if (window.innerWidth < 640) {
      // მობილურზე გადავამისამართოთ checkout გვერდზე
      window.location.href = '/checkout';
    } else {
      // დესკტოპზე გავხსნათ სიდებარი ჩექაუთის ფორმით
      setShowRightSidebar(true);
      setShowCheckoutForm(true);
    }
  };

  if (loading) {
    return (
      <div className="container mx-auto px-4 py-8">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-gray-900 mx-auto mb-4"></div>
          <p>იტვირთება...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="container mx-auto px-4 py-8">
        <div className="text-center">
          <div className="text-red-500 mb-4">{error}</div>
          <button
            onClick={() => window.location.reload()}
            className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
          >
            ხელახლა ცდა
          </button>
        </div>
      </div>
    );
  }

  if (!product) {
    return <div>პროდუქტი ვერ მოიძებნა</div>;
  }

  return (
    <div className="container mx-auto px-4 py-8 mb-20 lg:mb-8" style={{ width: '100%' }}>
      {showConfetti && (
        <Confetti
          width={window.innerWidth}
          height={window.innerHeight}
          numberOfPieces={200}
          recycle={false}
          colors={['#ff0000', '#00ff00', '#ffffff', '#gold']}
          style={{
            position: 'fixed',
            top: 0,
            left: 0,
            zIndex: 9999,
            pointerEvents: 'none'
          }}
        />
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16" style={{ display: 'grid' }}>
        <div className="space-y-4" style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <div className="relative" style={{ width: '100%', paddingBottom: '100%' }}>
            <div className="absolute inset-0 rounded-lg overflow-hidden">
              <img
                src={product.images[activeImage]?.src || '/wp-content/uploads/2024/11/placeholder-1.png'}
                alt={product.name}
                className="w-full h-full object-cover"
                style={{ display: 'block', width: '100%', height: '100%' }}
                onError={(e) => {
                  e.target.src = '/wp-content/uploads/2024/11/placeholder-1.png';
                }}
              />
            </div>
          </div>

          {product.images.length > 1 && (
            <div
              className="grid grid-cols-4 gap-2"
              style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '0.5rem' }}
            >
              {product.images.map((image, index) => (
                <button
                  key={image.id}
                  onClick={() => setActiveImage(index)}
                  className={`relative overflow-hidden border-2 ${
                    activeImage === index ? 'border-orange-500' : 'border-transparent'
                  }`}
                  style={{ paddingBottom: '100%' }}
                >
                  <div className="absolute inset-0">
                    <img
                      src={image.src}
                      alt={`${product.name} - ${index + 1}`}
                      className="w-full h-full object-cover"
                      style={{ display: 'block', width: '100%', height: '100%' }}
                      onError={(e) => {
                        e.target.src = '/wp-content/uploads/2024/11/placeholder-1.png';
                      }}
                    />
                  </div>
                </button>
              ))}
            </div>
          )}
        </div>

        <div style={{ display: 'flex', flexDirection: 'column' }}>
          <div className="text-sm text-gray-500 mb-2" style={{ marginBottom: '0.5rem' }}>
            {product.categories?.[0]?.name || 'Uncategorized'}
          </div>
          <h1 className="text-3xl font-medium text-gray-900 mb-4" style={{ marginBottom: '1rem' }}>
            {product.name}
          </h1>

          <div className="flex items-center gap-4 mb-6" style={{ display: 'flex', alignItems: 'center', gap: '1rem', marginBottom: '1.5rem' }}>
            {product.regular_price !== product.price && (
              <span className="text-gray-400 line-through text-xl">
                {product.regular_price}₾
              </span>
            )}
            <span className="text-2xl font-medium text-gray-900">
              {product.price}₾
            </span>
            {product.on_sale && (
              <span className="bg-[#ad2421] text-white text-sm px-2 py-1 rounded-md">
                ფასდაკლება {calculateDiscount(product.regular_price, product.price)}%
              </span>
            )}
          </div>

          <div
            className="prose prose-sm mb-6"
            dangerouslySetInnerHTML={{ __html: product.description }}
            style={{ marginBottom: '1.5rem' }}
          />

          <div className="mb-6">
            <div className="flex items-center gap-2">
              <span className="font-medium">მარაგის სტატუსი:</span>
              {product.stock_status === 'instock' ? (
                <span className="text-green-600">მარაგშია</span>
              ) : (
                <span className="text-red-600">არ არის მარაგში</span>
              )}
            </div>
          </div>

          {product.stock_status === 'instock' && (
            <button
              onClick={handleAddToCart}
              className={`hidden lg:flex w-full md:w-auto px-8 h-12 items-center justify-center gap-4 bg-[#1a691a] rounded-full transition-colors ${
                isAdding ? 'opacity-75' : ''
              }`}
              disabled={isAdding}
            >
              <span className='text-white font-bold'>
                {isAdding ? 'ემატება...' : `${product.price}₾`}
              </span>
              {!isAdding && (
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  className="w-5 h-5 text-white"
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
          )}
        </div>
      </div>

      {relatedProducts.length > 0 && (
        <div style={{ width: '100%' }}>
          <h2 className="text-2xl font-medium text-gray-900 mb-6" style={{ marginBottom: '1.5rem' }}>
            მსგავსი პროდუქტები
          </h2>
          <div
            className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6"
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))',
              gap: '1.5rem'
            }}
          >
            {relatedProducts.map((relatedProduct) => (
              <div
                key={relatedProduct.id}
                className="bg-white rounded-lg overflow-hidden shadow-sm"
                style={{ display: 'flex', flexDirection: 'column' }}
              >
                <Link
                  to={`/product/${relatedProduct?.slug || createSlug(relatedProduct.name)}?id=${relatedProduct.id}`}
                  state={{ productId: relatedProduct.id }}
                  style={{ display: 'flex', flexDirection: 'column', flex: 1 }}
                >
                  <div className="relative" style={{ paddingBottom: '100%' }}>
                    <div className="absolute inset-0">
                      <img
                        src={relatedProduct.images[0]?.src || '/wp-content/uploads/2024/11/placeholder-1.png'}
                        alt={relatedProduct.name}
                        className="w-full h-full object-cover bg-gray-100"
                        style={{ display: 'block', width: '100%', height: '100%' }}
                        onError={(e) => {
                          e.target.src = '/wp-content/uploads/2024/11/placeholder-1.png';
                        }}
                      />
                    </div>
                    {relatedProduct.on_sale && (
                      <div className="absolute top-2 left-2 bg-[#ad2421] text-white text-xs px-2 py-1 rounded-md">
                        ფასდაკლება {calculateDiscount(relatedProduct.regular_price, relatedProduct.price)}%
                      </div>
                    )}
                  </div>

                  <div className="p-4" style={{ padding: '1rem' }}>
                    <div className="text-xs text-gray-500 mb-1">
                      {relatedProduct.categories?.[0]?.name || 'Uncategorized'}
                    </div>
                    <h3 className="font-medium text-gray-900 mb-2 line-clamp-2">
                      {relatedProduct.name}
                    </h3>

                    <div className="flex items-center gap-2">
                      {relatedProduct.regular_price !== relatedProduct.price && (
                        <span className="text-gray-400 line-through text-sm">
                          {relatedProduct.regular_price}₾
                        </span>
                      )}
                      <span className="font-medium text-gray-900">
                        {relatedProduct.price}₾
                      </span>
                    </div>
                  </div>
                </Link>
              </div>
            ))}
          </div>
        </div>
      )}

      {product?.stock_status === 'instock' && (
        <div className="lg:hidden fixed bottom-[60px] left-0 right-0">
          <div className="bg-white border-t border-gray-200 px-4 py-3">
            <div className="flex gap-3">
              <button
                onClick={handleBackToProducts}
                className="h-10 px-4 flex items-center justify-center gap-2 bg-gray-100 rounded-lg transition-colors"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  className="h-4 w-4 text-gray-600"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M10 19l-7-7m0 0l7-7m-7 7h18"
                  />
                </svg>
                <span className="text-sm font-medium text-gray-600">უკან</span>
              </button>

              <button
                onClick={handleAddToCart}
                className={`flex-1 h-10 flex items-center justify-center gap-2 bg-[#1a691a] rounded-lg transition-colors ${
                  isAdding ? 'opacity-75' : ''
                }`}
                disabled={isAdding}
              >
                <span className='text-white text-sm font-medium'>
                  {isAdding ? 'ემატება...' : `${product.price}₾`}
                </span>
                {!isAdding && (
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    className="w-4 h-4 text-white"
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
        </div>
      )}
    </div>
  );
};

export default SingleProduct;