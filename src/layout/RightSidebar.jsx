import React, { useState, useEffect } from 'react';
import CheckoutForm from './CheckoutForm';
import { useCart } from '../context/CartContext';
import { Link, useNavigate } from 'react-router-dom';

const RightSidebar = () => {
  const { cartItems, updateQuantity, removeFromCart, calculateTotal, clearCart, formatPrice, setShowRightSidebar, refreshCart } = useCart();
  const [showCheckoutForm, setShowCheckoutForm] = useState(false);
  const navigate = useNavigate();
  
  useEffect(() => {
    refreshCart();
    
    const interval = setInterval(() => {
      refreshCart();
    }, 30000);

    return () => clearInterval(interval);
  }, [refreshCart]);

  const totalWithTaxAndShipping = (calculateTotal()).toFixed(2);

  const handleOrderSuccess = () => {
    clearCart();
    setShowCheckoutForm(false);
    setShowRightSidebar(false);
  };

  const handleCheckout = async () => {
    // Safari-ს დეტექცია
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
    
    if (window.innerWidth < 640) {
      // მობილურზე გადავამისამართოთ checkout გვერდზე
      window.location.href = '/checkout';
    } else if (isSafari) {
      // Safari-ში დესკტოპზეც გადავამისამართოთ checkout გვერდზე
      window.location.href = '/checkout';
    } else {
      // სხვა ბრაუზერებში დესკტოპზე გავხსნათ ჩექაუთის ფორმა სიდებარში
      setShowCheckoutForm(true);
    }
  };

  return (
    <div className="w-full bg-white flex flex-col h-full">
      {/* კალათის სათაური */}
      <div className="hidden sm:block p-4 border-b h-[10vh]">
        <div className='flex items-center justify-between h-full'>
          <h2 className="text-lg font-medium">
            თქვენი კალათა
          </h2>
          <span className='text-[#1a691a] text-lg sm:text-2xl'>
            {cartItems.length}
          </span>
        </div>
      </div>

      {/* Main Content */}
      <div className={`flex-1 pl-3 sm:pl-4 py-3 sm:py-4 flex flex-col overflow-y-auto ${!showCheckoutForm ? 'max-h-fit' : 'h-full'}`}>
        {!showCheckoutForm && (
          <div className='flex-1'>
            <div className="space-y-3 sm:space-y-4 pr-3 sm:pr-4 pb-20">
              {cartItems.map((item) => (
                <div key={item.id} className="flex flex-col bg-white rounded-lg shadow-sm">
                  {/* პროდუქტის ინფორმაცია */}
                  <div className="flex items-center gap-3 p-3">
                    <img 
                      src={item.image} 
                      alt={item.name}
                      className="w-14 h-14 sm:w-16 sm:h-16 object-cover rounded-lg bg-gray-100"
                    />
                    <div className="flex-1 min-w-0">
                      <h4 className="font-medium text-sm sm:text-base truncate">{item.name}</h4>
                      <div className="text-[#1a691a] text-sm sm:text-base font-medium mt-1">
                        {formatPrice(item.price * item.quantity)}₾
                      </div>
                    </div>
                  </div>

                  {/* რაოდენობის და წაშლის ღილაკები */}
                  <div className="flex items-center justify-between border-t p-2">
                    <div className="flex items-center gap-4 bg-gray-50 px-4 py-2 rounded-lg">
                      <button 
                        onClick={() => updateQuantity(item.id, Math.max(1, item.quantity - 1))}
                        disabled={item.quantity <= 1}
                        className="w-8 h-8 rounded-full bg-white shadow-sm flex items-center justify-center text-gray-600 disabled:opacity-50 text-lg font-medium"
                      >
                        -
                      </button>
                      <span className="text-base font-medium min-w-[20px] text-center">
                        {item.quantity}
                      </span>
                      <button 
                        onClick={() => updateQuantity(item.id, item.quantity + 1)}
                        className="w-8 h-8 rounded-full bg-white shadow-sm flex items-center justify-center text-gray-600 text-lg font-medium"
                      >
                        +
                      </button>
                    </div>
                    
                    <button 
                      onClick={() => removeFromCart(item.id)}
                      className="flex items-center gap-2 px-4 py-2 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 transition-colors duration-200"
                    >
                      <svg 
                        xmlns="http://www.w3.org/2000/svg" 
                        className="h-5 w-5" 
                        fill="none" 
                        viewBox="0 0 24 24" 
                        stroke="currentColor"
                      >
                        <path 
                          strokeLinecap="round" 
                          strokeLinejoin="round" 
                          strokeWidth={2} 
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" 
                        />
                      </svg>
                      <span className="text-sm font-medium">წაშლა</span>
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {showCheckoutForm && (
          <div className="h-full flex flex-col">
            <div className="mb-4">
              <button 
                onClick={() => setShowCheckoutForm(false)}
                className="flex items-center text-gray-600 hover:text-gray-800 text-sm sm:text-base"
              >
                <svg 
                  className="w-4 h-4 sm:w-5 sm:h-5 mr-2" 
                  fill="none" 
                  stroke="currentColor" 
                  viewBox="0 0 24 24"
                >
                  <path 
                    strokeLinecap="round" 
                    strokeLinejoin="round" 
                    strokeWidth="2" 
                    d="M15 19l-7-7 7-7"
                  />
                </svg>
                კალათაში დაბრუნება
              </button>
            </div>
            <div className="flex-1">
              <CheckoutForm 
                cartItems={cartItems}
                total={totalWithTaxAndShipping}
                onOrderSuccess={handleOrderSuccess}
              />
            </div>
          </div>
        )}
      </div>

      {/* Footer/Checkout Section */}
      <div className='sticky bottom-0 bg-white p-3 sm:p-4 border-t mt-auto'>
        {cartItems.length > 0 && !showCheckoutForm && (
          <div className="space-y-3">
            <div className="flex justify-between text-xs sm:text-sm">
              <span className="text-gray-500">ჯამი</span>
              <span className="text-gray-900">{calculateTotal()}₾</span>
            </div>
            <div className="flex justify-between font-semibold text-sm sm:text-base">
              <span>სრული ღირებულება</span>
              <span>{calculateTotal()}₾</span>
            </div>

            <button 
              className="w-full bg-[#1a691a] hover:bg-[#0f3a0d] text-white py-2.5 sm:py-3 rounded-lg transition-colors duration-200 text-sm sm:text-base"
              onClick={handleCheckout}
            >
              შეკვეთის გაფორმება
            </button>
          </div>
        )}

        {cartItems.length === 0 && (
          <div className="text-center py-6 sm:py-8">
            <svg 
              className="w-12 h-12 sm:w-16 sm:h-16 mx-auto text-gray-400 mb-3 sm:mb-4"
              fill="none" 
              stroke="currentColor" 
              viewBox="0 0 24 24"
            >
              <path 
                strokeLinecap="round" 
                strokeLinejoin="round" 
                strokeWidth="2" 
                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" 
              />
            </svg>
            <p className="text-gray-500 text-sm sm:text-base">თქვენი კალათა ცარიელია</p>
            <Link 
              to="/"
              className="mt-4 inline-block text-[#1a691a] hover:text-[#0f3a0d] font-medium"
            >
              მაღაზიაში დაბრუნება
            </Link>
          </div>
        )}
      </div>
    </div>
  );
};

export default RightSidebar;