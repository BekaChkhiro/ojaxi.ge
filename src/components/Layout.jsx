import React, { useState } from 'react';
import { useCart } from '../context/CartContext';
import LeftSidebar from '../layout/LeftSidebar';
import TopBar from '../layout/TopBar';
import RightSidebar from '../layout/RightSidebar';
import MenuBar from '../icons/menu_bar.svg';
import CartIcon from '../icons/cart_icon.svg'
import OjaxiLogo from '../images/ojaxi_logo.webp';
import { Link } from 'react-router-dom';

const Layout = () => {
  const { cartItems, showRightSidebar, setShowRightSidebar } = useCart();
  const [showLeftSidebar, setShowLeftSidebar] = useState(false);

  return (
    <div className="relative h-screen bg-gray-100 overflow-hidden">
      {/* Desktop Layout */}
      <div className="hidden lg:flex h-full">
        <div className="w-2/12 h-full overflow-y-auto">
          <LeftSidebar />
        </div>
        <div className="w-7/12 h-full overflow-y-auto">
          <TopBar />
        </div>
        <div className="w-3/12 h-full overflow-y-auto">
          <RightSidebar />
        </div>
      </div>

      {/* Mobile/Tablet Layout */}
      <div className="flex flex-col h-full lg:hidden">
        <div className="flex-1 relative">
          <TopBar />
        </div>

        {/* Mobile Left Sidebar Overlay */}
        {showLeftSidebar && (
          <>
            <div 
              className="fixed inset-0 bg-black bg-opacity-50 z-20"
              onClick={() => setShowLeftSidebar(false)}
            />
            <div className="fixed inset-x-0 bottom-0 z-30 bg-white rounded-t-3xl max-h-[85vh] overflow-hidden">
              <div className="flex items-center justify-between p-4 border-b">
                <h2 className="text-lg font-medium">მენიუ</h2>
                <button
                  onClick={() => setShowLeftSidebar(false)}
                  className="w-8 h-8 flex items-center justify-center bg-red-500 rounded-full"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    className="h-5 w-5 text-white"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M6 18L18 6M6 6l12 12"
                    />
                  </svg>
                </button>
              </div>
              <div className="overflow-y-auto" style={{ maxHeight: 'calc(85vh - 73px)' }}>
                <LeftSidebar />
              </div>
            </div>
          </>
        )}

        {/* Mobile Right Sidebar Overlay */}
        {showRightSidebar && (
          <>
            <div 
              className="fixed inset-0 bg-black bg-opacity-50 z-20"
              onClick={() => setShowRightSidebar(false)}
            />
            <div className="fixed inset-x-0 bottom-0 z-30 bg-white rounded-t-3xl h-[85vh] overflow-hidden">
              <div className="flex items-center justify-between p-4 border-b">
                <h2 className="text-lg font-medium">თქვენი კალათა</h2>
                <button
                  onClick={() => setShowRightSidebar(false)}
                  className="w-8 h-8 flex items-center justify-center bg-red-500 rounded-full"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    className="h-5 w-5 text-white"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M6 18L18 6M6 6l12 12"
                    />
                  </svg>
                </button>
              </div>
              <div className="h-[calc(85vh-73px)] overflow-y-auto">
                <RightSidebar />
              </div>
            </div>
          </>
        )}

        {/* Mobile Bottom Navigation */}
        <div className="fixed bottom-0 left-0 right-0 z-10 bg-white border-t border-gray-200 flex justify-between px-3 py-2">
          <button
            onClick={() => setShowLeftSidebar(true)}
            className="flex items-center justify-center p-2 rounded-lg bg-gray-100 hover:bg-gray-200 w-[32%]"
          >
            <img src={MenuBar} alt='Menu Bar Icon' className='w-6 h-6' />
          </button>
          
          <Link
            to="/"
            className="flex items-center justify-center p-2 rounded-lg bg-gray-100 hover:bg-gray-200 w-[32%]"
          >
            <img src={OjaxiLogo} alt="Ojaxi Logo" className='w-6' />
          </Link>
          
          <button
            onClick={() => setShowRightSidebar(true)}
            className="flex items-center justify-center p-2 rounded-lg bg-gray-100 hover:bg-gray-200 w-[32%] relative"
          >
            <img src={CartIcon} alt="Cart Icon" className='w-6 h-6'/>
            {cartItems.length > 0 && (
              <span className="absolute -top-2 -right-2 bg-[#1a691a] text-white w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium">
                {cartItems.length}
              </span>
            )}
          </button>
        </div>
      </div>
    </div>
  );
};

export default Layout;