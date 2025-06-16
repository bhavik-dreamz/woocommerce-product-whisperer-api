
import React from 'react';
import ProductSearch from '../components/ProductSearch';
import RecommendationEngine from '../components/RecommendationEngine';
import Header from '../components/Header';

const Index = () => {
  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
      <Header />
      <main className="container mx-auto px-4 py-8">
        <div className="text-center mb-12">
          <h1 className="text-4xl font-bold text-gray-900 mb-4">
            Product Recommendation API
          </h1>
          <p className="text-xl text-gray-600 max-w-2xl mx-auto">
            Discover products with intelligent suggestions and recommendations powered by our advanced API system
          </p>
        </div>
        
        <div className="space-y-12">
          <ProductSearch />
          <RecommendationEngine />
        </div>
      </main>
    </div>
  );
};

export default Index;
