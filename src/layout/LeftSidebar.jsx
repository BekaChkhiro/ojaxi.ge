import React from 'react';
import { Link } from 'react-router-dom';
import HomeIcon from '../icons/home.svg';
import TermsAndConditionsIcon from '../icons/terms-and-conditions.svg'
import ContactIcon from '../icons/contact.svg';
import MainLogo from '../images/ojaxi_logo.webp';

const LeftSidebar = () => {
  return (
    <div className="w-full h-full bg-white flex flex-col">
      {/* Desktop Logo - მხოლოდ დესკტოპზე */}
      <div className="hidden lg:flex p-4 justify-start items-center border-b h-[10vh]">
        <a href='/' className="block">
          <img 
            src={MainLogo} 
            className='w-10 h-10 transition-transform hover:scale-105' 
            alt="Ojaxi Logo" 
          />
        </a>
      </div>
      
      {/* Navigation Links */}
      <nav className="py-2 flex-1">
        <Link 
          to="/" 
          className="flex items-center px-4 py-3 text-gray-600 hover:bg-[#dcf6db7c] transition-colors duration-200"
        >
          <img 
            src={HomeIcon} 
            alt="Home Icon" 
            className='w-5 h-5 mr-4' 
          />
          <span className="text-base">მთავარი</span>
        </Link>

        <Link 
          to="/terms-and-condition" 
          className="flex items-center px-4 py-3 text-gray-600 hover:bg-[#dcf6db7c] transition-colors duration-200"
        >
          <img 
            src={TermsAndConditionsIcon} 
            alt="Terms and Conditions Icon" 
            className='w-5 h-5 mr-4' 
          />
          <span className="text-base">წესები და პირობები</span>
        </Link>
        
        <Link 
          to="/contact" 
          className="flex items-center px-4 py-3 text-gray-600 hover:bg-[#dcf6db7c] transition-colors duration-200"
        >
          <img 
            src={ContactIcon} 
            alt="Contact Icon" 
            className='w-5 h-5 mr-4' 
          />
          <span className="text-base">კონტაქტი</span>
        </Link>
      </nav>

      <div className="flex justify-start">
        <a 
          href='https://infinity.ge/ვებ-საიტის-დამზადება/' 
          className="flex items-center px-4 py-3 text-gray-600 hover:text-[#1a691a] transition-colors duration-200"
        >
          <span className="text-base">საიტების დამზადება</span>  
        </a>
      </div>
    </div>
  );
};

export default LeftSidebar;