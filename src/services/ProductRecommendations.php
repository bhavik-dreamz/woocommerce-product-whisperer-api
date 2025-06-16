
<?php

/**
 * Main class to integrate with WooCommerce
 */
class ProductRecommendations {
    
    private $engine;
    
    public function __construct() {
        $this->engine = new SimilarProductsEngine();
        
        // Hook into WooCommerce
        add_action('woocommerce_output_related_products_args', [$this, 'modify_related_products']);
        add_action('woocommerce_single_product_summary', [$this, 'display_ai_recommendations'], 25);
        
        // Add admin settings
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // AJAX endpoints
        add_action('wp_ajax_get_similar_products', [$this, 'ajax_get_similar_products']);
        add_action('wp_ajax_nopriv_get_similar_products', [$this, 'ajax_get_similar_products']);
    }
    
    /**
     * Modify WooCommerce related products to use our engine
     */
    public function modify_related_products($args) {
        global $product;
        
        if (!$product) return $args;
        
        $similar_products = $this->engine->get_similar_products(
            $product->get_id(), 
            $args['posts_per_page'] ?? 4
        );
        
        if (!empty($similar_products)) {
            $product_ids = array_column($similar_products, 'product_id');
            $args['post__in'] = $product_ids;
            $args['orderby'] = 'post__in'; // Maintain our custom order
        }
        
        return $args;
    }
    
    /**
     * Display AI-powered recommendations on product page
     */
    public function display_ai_recommendations() {
        global $product;
        
        if (!$product) return;
        
        $recommendations = $this->engine->get_similar_products($product->get_id(), 4);
        
        if (empty($recommendations)) return;
        
        echo '<div class="ai-recommendations">';
        echo '<h3>' . __('AI Recommended Products', 'textdomain') . '</h3>';
        echo '<div class="ai-products-grid">';
        
        foreach ($recommendations as $rec) {
            $rec_product = wc_get_product($rec['product_id']);
            if ($rec_product) {
                wc_get_template_part('content', 'product', ['product' => $rec_product]);
            }
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * AJAX handler for getting similar products
     */
    public function ajax_get_similar_products() {
        check_ajax_referer('similar_products_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id']);
        $limit = intval($_POST['limit']) ?: 4;
        
        if (!$product_id) {
            wp_die('Invalid product ID');
        }
        
        $similar_products = $this->engine->get_similar_products($product_id, $limit);
        
        $response = [];
        foreach ($similar_products as $similar) {
            $product = wc_get_product($similar['product_id']);
            if ($product) {
                $response[] = [
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'price' => $product->get_price_html(),
                    'permalink' => $product->get_permalink(),
                    'image' => $product->get_image('woocommerce_thumbnail'),
                    'score' => round($similar['score'], 2)
                ];
            }
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Add admin menu for configuration
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'AI Product Recommendations',
            'AI Recommendations',
            'manage_woocommerce',
            'ai-product-recommendations',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Admin page for configuration
     */
    public function admin_page() {
        if (isset($_POST['save_settings'])) {
            $this->save_admin_settings();
        }
        
        include plugin_dir_path(__FILE__) . 'admin/recommendations-admin.php';
    }
    
    private function save_admin_settings() {
        // Save admin settings logic here
        $settings = [
            'similarity_weights' => $_POST['similarity_weights'] ?? [],
            'cache_expiry' => intval($_POST['cache_expiry']) ?: 3600,
            'min_similarity_score' => floatval($_POST['min_similarity_score']) ?: 0.1,
        ];
        
        update_option('ai_recommendations_settings', $settings);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        });
    }
}

// Initialize the recommendations system
new ProductRecommendations();
