
<?php

class SimilarProductsEngine {
    
    private $similarity_weights = [
        'category' => 0.4,
        'tags' => 0.2,
        'attributes' => 0.25,
        'price_range' => 0.1,
        'brand' => 0.15,
        'semantic' => 0.3
    ];
    
    private $cache_group = 'similar_products';
    private $cache_expiry = 3600; // 1 hour
    
    /**
     * Get similar products with advanced matching and scoring
     */
    public function get_similar_products($product_id, $limit = 4, $options = []) {
        // Validate input
        $product = wc_get_product($product_id);
        if (!$product || !$this->is_valid_product($product)) {
            return [];
        }
        
        // Check cache first
        $cache_key = "similar_products_{$product_id}_{$limit}";
        $cached_result = wp_cache_get($cache_key, $this->cache_group);
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        // Get product features for matching
        $product_features = $this->extract_product_features($product);
        
        // Get candidate products
        $candidates = $this->get_candidate_products($product_id, $product_features, $limit * 3);
        
        // Score and rank candidates
        $scored_products = $this->score_similarity($product_features, $candidates, $product_id);
        
        // Apply business rules and filters
        $filtered_products = $this->apply_business_filters($scored_products, $product);
        
        // Get top matches
        $similar_products = array_slice($filtered_products, 0, $limit);
        
        // Cache the result
        wp_cache_set($cache_key, $similar_products, $this->cache_group, $this->cache_expiry);
        
        return $similar_products;
    }
    
    /**
     * Extract comprehensive product features for matching
     */
    private function extract_product_features($product) {
        $product_id = $product->get_id();
        
        return [
            'categories' => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']),
            'tags' => wp_get_post_terms($product_id, 'product_tag', ['fields' => 'ids']),
            'attributes' => $this->get_product_attributes($product),
            'price' => (float) $product->get_price(),
            'price_range' => $this->get_price_range($product->get_price()),
            'brand' => $this->get_product_brand($product),
            'keywords' => $this->extract_keywords($product),
            'meta_attributes' => $this->get_meta_attributes($product_id),
            'reviews_avg' => (float) $product->get_average_rating(),
            'sales_rank' => $this->get_sales_rank($product_id)
        ];
    }
    
    /**
     * Get candidate products using optimized queries
     */
    private function get_candidate_products($product_id, $features, $candidate_limit) {
        global $wpdb;
        
        // Build dynamic query based on available features
        $where_conditions = [];
        $join_conditions = [];
        
        // Category matching (primary)
        if (!empty($features['categories'])) {
            $category_ids = implode(',', array_map('intval', $features['categories']));
            $join_conditions[] = "LEFT JOIN {$wpdb->term_relationships} tr_cat ON p.ID = tr_cat.object_id";
            $where_conditions[] = "tr_cat.term_taxonomy_id IN ({$category_ids})";
        }
        
        // Price range matching
        if ($features['price'] > 0) {
            $price_min = $features['price'] * 0.5;
            $price_max = $features['price'] * 2.0;
            $join_conditions[] = "LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'";
            $where_conditions[] = "CAST(pm_price.meta_value AS DECIMAL(10,2)) BETWEEN {$price_min} AND {$price_max}";
        }
        
        // Build the query
        $joins = implode(' ', array_unique($join_conditions));
        $where = implode(' OR ', $where_conditions);
        
        $query = "
            SELECT DISTINCT p.ID, p.post_title, p.post_content, p.post_excerpt
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock_status'
            LEFT JOIN {$wpdb->postmeta} pm_visibility ON p.ID = pm_visibility.post_id AND pm_visibility.meta_key = '_visibility'
            {$joins}
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND p.ID != {$product_id}
            AND pm_stock.meta_value = 'instock'
            AND pm_visibility.meta_value IN ('catalog', 'visible')
            AND ({$where})
            LIMIT {$candidate_limit}
        ";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Score similarity using weighted algorithms
     */
    private function score_similarity($base_features, $candidates, $base_product_id) {
        $scored_products = [];
        
        foreach ($candidates as $candidate) {
            $candidate_features = $this->extract_product_features(wc_get_product($candidate->ID));
            
            $similarity_score = 0;
            
            // Category similarity
            $category_score = $this->calculate_category_similarity(
                $base_features['categories'], 
                $candidate_features['categories']
            );
            $similarity_score += $category_score * $this->similarity_weights['category'];
            
            // Tag similarity
            $tag_score = $this->calculate_jaccard_similarity(
                $base_features['tags'], 
                $candidate_features['tags']
            );
            $similarity_score += $tag_score * $this->similarity_weights['tags'];
            
            // Attribute similarity
            $attribute_score = $this->calculate_attribute_similarity(
                $base_features['attributes'], 
                $candidate_features['attributes']
            );
            $similarity_score += $attribute_score * $this->similarity_weights['attributes'];
            
            // Price similarity
            $price_score = $this->calculate_price_similarity(
                $base_features['price'], 
                $candidate_features['price']
            );
            $similarity_score += $price_score * $this->similarity_weights['price_range'];
            
            // Brand similarity
            $brand_score = $this->calculate_brand_similarity(
                $base_features['brand'], 
                $candidate_features['brand']
            );
            $similarity_score += $brand_score * $this->similarity_weights['brand'];
            
            // Semantic similarity (title/description)
            $semantic_score = $this->calculate_semantic_similarity(
                $base_features['keywords'], 
                $candidate_features['keywords']
            );
            $similarity_score += $semantic_score * $this->similarity_weights['semantic'];
            
            $scored_products[] = [
                'product_id' => $candidate->ID,
                'score' => $similarity_score,
                'category_score' => $category_score,
                'tag_score' => $tag_score,
                'attribute_score' => $attribute_score,
                'price_score' => $price_score,
                'brand_score' => $brand_score,
                'semantic_score' => $semantic_score
            ];
        }
        
        // Sort by similarity score
        usort($scored_products, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return $scored_products;
    }
    
    /**
     * Calculate category hierarchy similarity
     */
    private function calculate_category_similarity($base_categories, $candidate_categories) {
        if (empty($base_categories) || empty($candidate_categories)) {
            return 0;
        }
        
        $base_hierarchy = $this->get_category_hierarchy($base_categories);
        $candidate_hierarchy = $this->get_category_hierarchy($candidate_categories);
        
        $intersection = array_intersect($base_hierarchy, $candidate_hierarchy);
        $union = array_unique(array_merge($base_hierarchy, $candidate_hierarchy));
        
        // Weight deeper categories more heavily
        $weighted_score = 0;
        foreach ($intersection as $category_id) {
            $depth = $this->get_category_depth($category_id);
            $weighted_score += (1 + $depth * 0.5);
        }
        
        return count($union) > 0 ? $weighted_score / count($union) : 0;
    }
    
    /**
     * Calculate Jaccard similarity coefficient
     */
    private function calculate_jaccard_similarity($set1, $set2) {
        if (empty($set1) && empty($set2)) {
            return 1;
        }
        
        if (empty($set1) || empty($set2)) {
            return 0;
        }
        
        $intersection = array_intersect($set1, $set2);
        $union = array_unique(array_merge($set1, $set2));
        
        return count($intersection) / count($union);
    }
    
    /**
     * Calculate attribute similarity with fuzzy matching
     */
    private function calculate_attribute_similarity($base_attributes, $candidate_attributes) {
        if (empty($base_attributes) && empty($candidate_attributes)) {
            return 1;
        }
        
        if (empty($base_attributes) || empty($candidate_attributes)) {
            return 0;
        }
        
        $total_score = 0;
        $attribute_count = 0;
        
        foreach ($base_attributes as $attr_name => $base_values) {
            if (isset($candidate_attributes[$attr_name])) {
                $candidate_values = $candidate_attributes[$attr_name];
                
                // Calculate similarity for this attribute
                $attr_similarity = $this->calculate_jaccard_similarity($base_values, $candidate_values);
                $total_score += $attr_similarity;
                $attribute_count++;
            }
        }
        
        return $attribute_count > 0 ? $total_score / $attribute_count : 0;
    }
    
    /**
     * Calculate price similarity using exponential decay
     */
    private function calculate_price_similarity($base_price, $candidate_price) {
        if ($base_price <= 0 || $candidate_price <= 0) {
            return 0;
        }
        
        $price_diff = abs($base_price - $candidate_price);
        $relative_diff = $price_diff / max($base_price, $candidate_price);
        
        // Exponential decay: closer prices get higher scores
        return exp(-$relative_diff * 2);
    }
    
    /**
     * Calculate brand similarity
     */
    private function calculate_brand_similarity($base_brand, $candidate_brand) {
        if (empty($base_brand) && empty($candidate_brand)) {
            return 0.5; // Neutral score when both have no brand
        }
        
        if (empty($base_brand) || empty($candidate_brand)) {
            return 0.1; // Low score when only one has brand
        }
        
        return strtolower($base_brand) === strtolower($candidate_brand) ? 1 : 0;
    }
    
    /**
     * Calculate semantic similarity using TF-IDF-like approach
     */
    private function calculate_semantic_similarity($base_keywords, $candidate_keywords) {
        if (empty($base_keywords) || empty($candidate_keywords)) {
            return 0;
        }
        
        // Simple cosine similarity for keywords
        $intersection = array_intersect($base_keywords, $candidate_keywords);
        $magnitude_base = sqrt(count($base_keywords));
        $magnitude_candidate = sqrt(count($candidate_keywords));
        
        if ($magnitude_base == 0 || $magnitude_candidate == 0) {
            return 0;
        }
        
        return count($intersection) / ($magnitude_base * $magnitude_candidate);
    }
    
    /**
     * Apply business rules and filters
     */
    private function apply_business_filters($scored_products, $base_product) {
        $filtered = [];
        
        foreach ($scored_products as $scored_product) {
            $product = wc_get_product($scored_product['product_id']);
            
            if (!$product) continue;
            
            // Skip if minimum score not met
            if ($scored_product['score'] < 0.1) continue;
            
            // Business rules
            if ($this->passes_business_rules($product, $base_product)) {
                $filtered[] = $scored_product;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Check business rules
     */
    private function passes_business_rules($candidate_product, $base_product) {
        // Check if products are too similar (duplicate detection)
        if ($this->are_products_duplicates($candidate_product, $base_product)) {
            return false;
        }
        
        // Check stock status
        if ($candidate_product->get_stock_status() !== 'instock') {
            return false;
        }
        
        // Check visibility
        if (!in_array($candidate_product->get_catalog_visibility(), ['catalog', 'visible'])) {
            return false;
        }
        
        return apply_filters('similar_products_business_rules', true, $candidate_product, $base_product);
    }
    
    /**
     * Helper methods
     */
    private function is_valid_product($product) {
        return $product && $product->is_type(['simple', 'variable']) && $product->get_status() === 'publish';
    }
    
    private function get_product_attributes($product) {
        $attributes = [];
        foreach ($product->get_attributes() as $attribute) {
            $taxonomy = $attribute->get_name();
            $terms = wp_get_post_terms($product->get_id(), $taxonomy, ['fields' => 'ids']);
            if (!empty($terms)) {
                $attributes[$taxonomy] = $terms;
            }
        }
        return $attributes;
    }
    
    private function get_price_range($price) {
        if ($price < 25) return 'budget';
        if ($price < 100) return 'mid';
        if ($price < 500) return 'premium';
        return 'luxury';
    }
    
    private function get_product_brand($product) {
        // Try common brand taxonomies/meta
        $brand_meta_keys = ['_brand', 'brand', '_product_brand'];
        foreach ($brand_meta_keys as $key) {
            $brand = get_post_meta($product->get_id(), $key, true);
            if (!empty($brand)) return $brand;
        }
        
        // Try brand taxonomy
        $brand_terms = wp_get_post_terms($product->get_id(), 'product_brand');
        return !empty($brand_terms) ? $brand_terms[0]->name : '';
    }
    
    private function extract_keywords($product) {
        $text = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();
        $keywords = str_word_count(strtolower($text), 1);
        
        // Remove common stop words
        $stop_words = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
        $keywords = array_diff($keywords, $stop_words);
        
        // Filter by length and frequency
        $keyword_freq = array_count_values($keywords);
        $significant_keywords = array_keys(array_filter($keyword_freq, function($freq) {
            return $freq >= 1;
        }));
        
        return array_filter($significant_keywords, function($keyword) {
            return strlen($keyword) > 2;
        });
    }
    
    private function get_meta_attributes($product_id) {
        return get_post_meta($product_id);
    }
    
    private function get_sales_rank($product_id) {
        return (int) get_post_meta($product_id, 'total_sales', true);
    }
    
    private function get_category_hierarchy($category_ids) {
        $hierarchy = [];
        foreach ($category_ids as $cat_id) {
            $ancestors = get_ancestors($cat_id, 'product_cat');
            $hierarchy = array_merge($hierarchy, $ancestors, [$cat_id]);
        }
        return array_unique($hierarchy);
    }
    
    private function get_category_depth($category_id) {
        return count(get_ancestors($category_id, 'product_cat'));
    }
    
    private function are_products_duplicates($product1, $product2) {
        $similarity_threshold = 0.95;
        
        $title_similarity = similar_text(
            strtolower($product1->get_name()), 
            strtolower($product2->get_name())
        ) / 100;
        
        return $title_similarity > $similarity_threshold;
    }
}
