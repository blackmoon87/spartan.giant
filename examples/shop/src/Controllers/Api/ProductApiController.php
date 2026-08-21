<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use Spartan\Controller;
use App\Models\Product;

class ProductApiController extends Controller
{
    public function index(): void
    {
        $products = (new Product())->all();

        $this->json([
            'status' => 'success',
            'count'  => count($products),
            'data'   => $products,
        ]);
    }

    public function show(string $id): void
    {
        $product = (new Product())->findInstance((int)$id);
        if (!$product) {
            $this->json(['error' => 'Product not found.'], 404);
            return;
        }

        $this->json([
            'status' => 'success',
            'data'   => $product->toArray(),
        ]);
    }
}
