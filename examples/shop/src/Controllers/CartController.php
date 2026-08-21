<?php

declare(strict_types=1);

namespace App\Controllers;

use Spartan\Controller;
use App\Models\Product;

class CartController extends Controller
{
    public function index(): string
    {
        $cart = $this->session->get('cart', []);
        $items = [];
        $total = 0.0;

        foreach ($cart as $productId => $qty) {
            $product = (new Product())->findInstance((int)$productId);
            if ($product) {
                $subtotal = (float)$product->price * (int)$qty;
                $total += $subtotal;
                $items[] = [
                    'product'  => $product,
                    'quantity' => (int)$qty,
                    'subtotal' => $subtotal,
                ];
            }
        }

        return $this->render('shop/cart', [
            'title' => 'Shopping Cart',
            'items' => $items,
            'total' => $total,
        ]);
    }

    public function add(): void
    {
        $productId = (int)$this->request->post('product_id', 0);
        $quantity  = (int)$this->request->post('quantity', 1);

        if ($productId > 0 && $quantity > 0) {
            $cart = $this->session->get('cart', []);
            $cart[$productId] = ($cart[$productId] ?? 0) + $quantity;
            $this->session->set('cart', $cart);
        }

        if ($this->request->isAjax()) {
            $totalCount = array_sum($this->session->get('cart', []));
            $this->json(['success' => true, 'cartCount' => $totalCount]);
            return;
        }

        $this->session->setFlash('success', 'Item added to shopping cart!');
        $this->redirect('/cart');
    }
}
