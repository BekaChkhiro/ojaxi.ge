import React, { useState, useEffect } from 'react';
import { useCart } from '../context/CartContext';
import Christmas from '../images/christmas.gif';

const CheckoutForm = ({ onOrderSuccess }) => {
  const [isLoading, setIsLoading] = useState(true);
  const { clearCart } = useCart();

  useEffect(() => {
    window.location.href = '/checkout';
  }, []);

  return null;
};

export default CheckoutForm;