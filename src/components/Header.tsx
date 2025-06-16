
import React from 'react';
import { ShoppingBag, Search, Zap } from 'lucide-react';

const Header = () => {
  return (
    <header className="bg-white shadow-sm border-b">
      <div className="container mx-auto px-4 py-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-2">
            <div className="bg-gradient-to-r from-purple-600 to-blue-600 p-2 rounded-lg">
              <ShoppingBag className="h-6 w-6 text-white" />
            </div>
            <h1 className="text-xl font-bold text-gray-900">RecommendAPI</h1>
          </div>
          
          <nav className="hidden md:flex items-center space-x-6">
            <div className="flex items-center space-x-1 text-gray-600">
              <Search className="h-4 w-4" />
              <span className="text-sm">Search</span>
            </div>
            <div className="flex items-center space-x-1 text-gray-600">
              <Zap className="h-4 w-4" />
              <span className="text-sm">Recommendations</span>
            </div>
          </nav>
        </div>
      </div>
    </header>
  );
};

export default Header;
