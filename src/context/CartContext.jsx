import React, { createContext, useState, useContext, useEffect } from 'react';

export const CartContext = createContext({
  cartItems: [],
  addToCart: () => {},
  updateQuantity: () => {},
  removeFromCart: () => {},
  calculateTotal: () => {},
  showRightSidebar: false,
  setShowRightSidebar: () => {}
});

export const useCart = () => useContext(CartContext);

export const CartProvider = ({ children }) => {
  const [cartItems, setCartItems] = useState(() => {
    try {
      const localCart = localStorage.getItem('cartItems');
      return localCart ? JSON.parse(localCart) : [];
    } catch {
      return [];
    }
  });
  const [showRightSidebar, setShowRightSidebar] = useState(false);

  useEffect(() => {
    if (cartItems.length > 0) {
      localStorage.setItem('cartItems', JSON.stringify(cartItems));
    }
  }, [cartItems]);

  // დევცვალოთ formatPrice ფუნქცია
  const formatPrice = (price) => {
    return Number(price).toFixed(2);
  };

  // დავამატოთ ფუნქცია WooCommerce კალათის სინქრონიზაციისთვის
  const fetchWooCommerceCart = async () => {
    try {
      const response = await fetch(`${window.location.origin}/wp-json/wc/store/v1/cart`, {
        headers: {
          'Content-Type': 'application/json',
          'X-WC-Store-API-Nonce': window.wcStoreApiSettings?.nonce || '',
        },
        credentials: 'include'
      });

      if (!response.ok) throw new Error('Cart fetch failed');
      
      const data = await response.json();
      
      if (data.items) {
        const formattedItems = data.items.map(item => ({
          id: item.id,
          key: item.key,
          name: item.name,
          price: parseFloat(item.prices.price) / 100,
          quantity: item.quantity,
          image: item.images[0]?.src || '/placeholder.png'
        }));
        
        setCartItems(formattedItems);
        localStorage.setItem('cartItems', JSON.stringify(formattedItems));
      }
    } catch (error) {
      console.error('Error fetching cart:', error);
    }
  };

  // განვაახლოთ updateQuantity ფუნქცია
  const updateQuantity = async (productId, newQuantity) => {
    try {
      const item = cartItems.find(item => item.id === productId);
      if (!item) return;

      // ოპტიმისტური განახლება
      const updatedItems = cartItems.map(cartItem => 
        cartItem.id === productId 
          ? { ...cartItem, quantity: newQuantity }
          : cartItem
      );
      setCartItems(updatedItems);

      const response = await fetch(`${window.location.origin}/wp-json/wc/store/v1/cart/items/${item.key}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WC-Store-API-Nonce': window.wcStoreApiSettings?.nonce || '',
        },
        credentials: 'include',
        body: JSON.stringify({
          key: item.key,
          quantity: newQuantity
        })
      });

      if (!response.ok) throw new Error('Failed to update quantity');
      
      // განვაახლოთ კალათა სერვერის პასუხის მიხედვით
      await fetchWooCommerceCart();
    } catch (error) {
      console.error('Error updating quantity:', error);
      // შეცდომის შემთხვევაში დავაბრუნოთ რეალური მდგომარეობა
      await fetchWooCommerceCart();
    }
  };

  // განვაახლოთ removeFromCart ფუნქცია
  const removeFromCart = async (productId) => {
    try {
      const item = cartItems.find(item => item.id === productId);
      if (!item) return;

      // ოპტიმისტური წაშლა
      setCartItems(prev => prev.filter(cartItem => cartItem.id !== productId));

      const response = await fetch(`${window.location.origin}/wp-json/wc/store/v1/cart/items/${item.key}`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-WC-Store-API-Nonce': window.wcStoreApiSettings?.nonce || ''
        },
        credentials: 'include'
      });

      if (!response.ok) throw new Error('Failed to remove item');
      
      // განვაახლოთ კალათა სერვერის პასუხის მიხედვით
      await fetchWooCommerceCart();
    } catch (error) {
      console.error('Error removing item:', error);
      await fetchWooCommerceCart();
    }
  };

  // დავამატოთ useEffect კალათის სინქრონიზაციისთვის
  useEffect(() => {
    fetchWooCommerceCart();
  }, []);

  const addToCart = async (product) => {
    try {
      const response = await fetch(`${window.location.origin}/wp-json/wc/store/v1/cart/add-item`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WC-Store-API-Nonce': window.wcStoreApiSettings?.nonce || '',
        },
        credentials: 'include',
        body: JSON.stringify({
          id: product.id,
          quantity: 1
        })
      });

      if (!response.ok) {
        throw new Error('Failed to add to WooCommerce cart');
      }

      // განვაახლოთ კალათა წარმატებული დამატების შემდეგ
      await fetchWooCommerceCart();
      
      return true;
    } catch (error) {
      console.error('კალათაში დამატების შეცდომა:', error);
      throw error;
    }
  };

  const clearCart = async () => {
    try {
      if (!window.wcStoreApiSettings) {
        throw new Error('WC Store API settings not found');
      }

      const response = await fetch(`${window.location.origin}/wp-json/wc/store/v1/cart/items`, {
        method: 'DELETE',
        headers: {
          'X-WC-Store-API-Nonce': window.wcStoreApiSettings.nonce
        },
        credentials: 'include'
      });

      if (!response.ok) throw new Error('Failed to clear cart');

      setCartItems([]);
    } catch (error) {
      console.error('Error clearing cart:', error);
      alert('კალათის გასუფთავებისას დაფიქსირდა შეცდომა');
    }
  };

  const calculateTotal = () => {
    return cartItems.reduce((total, item) => total + (item.price * item.quantity), 0);
  };

  // დავამატოთ ახალი ფუნქცია კალათის განახლებისთვის
  const refreshCart = async () => {
    await fetchWooCommerceCart();
  };

  return (
    <CartContext.Provider value={{
      cartItems,
      addToCart,
      removeFromCart,
      updateQuantity,
      clearCart,
      calculateTotal,
      formatPrice,
      showRightSidebar,
      setShowRightSidebar,
      refreshCart: fetchWooCommerceCart
    }}>
      {children}
    </CartContext.Provider>
  );
};

export default CartProvider;