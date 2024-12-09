import React from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import Layout from './components/Layout';
import HomePage from './pages/HomePage';
import SingleProduct from './pages/SingleProduct';
import { CartProvider } from './context/CartContext';
import TermsAndConditions from './pages/TermsAndConditions';
import ContactPage from './pages/ContactPage';
import Gatamasheba from './pages/Gatamasheba';
function App() {
  return (
    <CartProvider>
      <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
        <Routes>
          <Route path="/" element={<Layout />}>
            <Route path="product/:name" element={<SingleProduct />} />
            <Route index element={<HomePage />} />
            <Route path='terms-and-condition' element={<TermsAndConditions />} />
            <Route path="contact" element={<ContactPage />} />
            <Route path="gatamasheba" element={<Gatamasheba />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </CartProvider>
  );
}

export default App;