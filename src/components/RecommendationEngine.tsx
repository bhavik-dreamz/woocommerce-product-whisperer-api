
import React, { useState } from 'react';
import { Zap, TrendingUp, Users, Heart } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import ProductCard from './ProductCard';
import { mockProducts } from '../data/mockProducts';

const RecommendationEngine = () => {
  const [selectedProduct, setSelectedProduct] = useState(mockProducts[0]);

  const getRecommendations = (type: string) => {
    switch (type) {
      case 'similar':
        return mockProducts
          .filter(p => p.category === selectedProduct.category && p.id !== selectedProduct.id)
          .slice(0, 3);
      case 'trending':
        return mockProducts
          .filter(p => p.rating >= 4.5)
          .sort(() => Math.random() - 0.5)
          .slice(0, 3);
      case 'popular':
        return mockProducts
          .sort((a, b) => b.reviews - a.reviews)
          .slice(0, 3);
      case 'recommended':
        return mockProducts
          .filter(p => p.onSale || p.isNew)
          .slice(0, 3);
      default:
        return [];
    }
  };

  return (
    <section className="bg-white rounded-xl shadow-lg p-6">
      <div className="flex items-center space-x-2 mb-6">
        <Zap className="h-5 w-5 text-purple-600" />
        <h2 className="text-2xl font-bold text-gray-900">Recommendation Engine</h2>
      </div>

      <div className="mb-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-3">Based on this product:</h3>
        <div className="max-w-sm">
          <ProductCard product={selectedProduct} />
        </div>
      </div>

      <Tabs defaultValue="similar" className="w-full">
        <TabsList className="grid w-full grid-cols-4">
          <TabsTrigger value="similar" className="flex items-center space-x-1">
            <Heart className="h-4 w-4" />
            <span className="hidden sm:inline">Similar</span>
          </TabsTrigger>
          <TabsTrigger value="trending" className="flex items-center space-x-1">
            <TrendingUp className="h-4 w-4" />
            <span className="hidden sm:inline">Trending</span>
          </TabsTrigger>
          <TabsTrigger value="popular" className="flex items-center space-x-1">
            <Users className="h-4 w-4" />
            <span className="hidden sm:inline">Popular</span>
          </TabsTrigger>
          <TabsTrigger value="recommended" className="flex items-center space-x-1">
            <Zap className="h-4 w-4" />
            <span className="hidden sm:inline">For You</span>
          </TabsTrigger>
        </TabsList>

        <TabsContent value="similar" className="mt-6">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {getRecommendations('similar').map(product => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </TabsContent>

        <TabsContent value="trending" className="mt-6">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {getRecommendations('trending').map(product => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </TabsContent>

        <TabsContent value="popular" className="mt-6">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {getRecommendations('popular').map(product => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </TabsContent>

        <TabsContent value="recommended" className="mt-6">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {getRecommendations('recommended').map(product => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </TabsContent>
      </Tabs>

      <div className="mt-8 text-center">
        <Button 
          onClick={() => setSelectedProduct(mockProducts[Math.floor(Math.random() * mockProducts.length)])}
          className="bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700"
        >
          Try Different Product
        </Button>
      </div>
    </section>
  );
};

export default RecommendationEngine;
