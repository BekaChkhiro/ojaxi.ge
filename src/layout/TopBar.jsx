import React from 'react';
import { Outlet } from 'react-router-dom';
import SearchBar from './topbar/SearchBar';

const TopBar = () => {
  return (
    <>
      {/* Top Header */}
      <header className="bg-white shadow-sm w-full z-10 topbar-header flex justify-center items-center p-4 border-x border-b">
            <SearchBar />
      </header>

      {/* Dynamic Content */}
      <main className="w-full overflow-scroll overflow-x-hidden scroll-smooth topbar-content p-4 pb-8">
        <Outlet />
      </main>
    </>
  );
};

export default TopBar;