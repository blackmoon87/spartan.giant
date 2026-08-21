<?php

declare(strict_types=1);

namespace App\Controllers;

use Spartan\Controller;
use App\Models\Category;
use App\Models\Product;

class ShopController extends Controller
{
    public function index(): string
    {
        $categories = (new Category())->all();
        
        // Eager load products for categories (demonstrates loadFor relationship without N+1)
        $categories = (new Category())->products()->loadFor($categories, as: 'products');
        
        // Featured products using QueryBuilder
        $featuredProducts = (new Product())->table()
            ->where('featured', 1)
            ->limit(6)
            ->get();

        return $this->render('shop/index', [
            'title'            => 'Spartan Shop — Next-Gen Tech Store',
            'categories'       => $categories,
            'featuredProducts' => $featuredProducts,
        ]);
    }

    public function catalog(): string
    {
        $categoryId = $this->request->get('category');
        $query      = (new Product())->table();

        if ($categoryId) {
            $query->where('category_id', (int)$categoryId);
        }

        $products   = $query->orderBy('name', 'ASC')->get();
        $categories = (new Category())->all();

        return $this->render('shop/catalog', [
            'title'            => 'Product Catalog',
            'products'         => $products,
            'categories'       => $categories,
            'selectedCategory' => $categoryId ? (int)$categoryId : null,
        ]);
    }

    public function search(): string
    {
        $keyword = trim((string)$this->request->post('query', ''));
        
        $qb = (new Product())->table();
        if ($keyword !== '') {
            $qb->where('name', "%{$keyword}%", 'LIKE');
        }

        $products = $qb->get();

        // Returns HTMX Partial Fragment without main layout wrapper
        return $this->renderViewOnly('shop/partials/product_grid', [
            'products' => $products,
        ]);
    }

    public function show(string $slug): string
    {
        $product = (new Product())->findInstanceBy('slug', $slug);
        if (!$product) {
            $this->response->setStatusCode(404);
            return $this->render('error_404', ['message' => 'Product not found.']);
        }

        return $this->render('shop/product', [
            'title'   => $product->name,
            'product' => $product,
        ]);
    }
}
