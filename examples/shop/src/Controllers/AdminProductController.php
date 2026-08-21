<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Requests\StoreProductRequest;
use Spartan\Attributes\RequirePermission;
use Spartan\Attributes\RequireRole;
use Spartan\Controller;
use App\Models\Category;
use App\Models\Product;

#[RequireRole('admin')]
#[RequirePermission('manage_products')]
class AdminProductController extends Controller
{
    public function index(): string
    {
        $products   = (new Product())->all();
        $categories = (new Category())->all();

        return $this->render('admin/products', [
            'title'      => 'Admin Product Management',
            'products'   => $products,
            'categories' => $categories,
        ]);
    }

    public function store(StoreProductRequest $request): void
    {
        $name        = (string)$request->post('name');
        $categoryId  = (int)$request->post('category_id');
        $price       = (float)$request->post('price');
        $stock       = (int)$request->post('stock');
        $description = (string)$request->post('description');
        $slug        = preg_replace('/[^a-z0-9-]+/', '-', strtolower($name));

        (new Product())->create([
            'category_id' => $categoryId,
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description,
            'price'       => $price,
            'stock'       => $stock,
            'featured'    => 1,
        ]);

        $this->session->setFlash('success', "Product '{$name}' created successfully!");
        $this->redirect('/admin/products');
    }
}
