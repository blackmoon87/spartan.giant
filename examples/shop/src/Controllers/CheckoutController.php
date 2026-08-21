<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Requests\CheckoutRequest;
use Spartan\Controller;
use App\Models\Product;
use App\Services\OrderService;

class CheckoutController extends Controller
{
    public function __construct(private OrderService $orderService)
    {
        parent::__construct();
    }

    public function show(): string
    {
        $cart = $this->session->get('cart', []);
        if (empty($cart)) {
            $this->session->setFlash('warning', 'Your cart is empty.');
            $this->redirect('/cart');
        }

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

        return $this->render('shop/checkout', [
            'title' => 'Checkout',
            'items' => $items,
            'total' => $total,
        ]);
    }

    public function process(CheckoutRequest $request): void
    {
        $cart = $this->session->get('cart', []);
        if (empty($cart)) {
            $this->session->setFlash('error', 'Your cart is empty.');
            $this->redirect('/cart');
        }

        $orderItems = [];
        foreach ($cart as $productId => $qty) {
            $orderItems[] = [
                'product_id' => (int)$productId,
                'quantity'   => (int)$qty,
            ];
        }

        $userId          = (int)$this->session->get('user_id', 2);
        $shippingAddress = (string)$request->post('shipping_address');
        $paymentMethod   = (string)$request->post('payment_method');

        $order = $this->orderService->createOrder($userId, $orderItems, $shippingAddress, $paymentMethod);

        // Clear cart
        $this->session->set('cart', []);

        // Dispatch sync and async events
        $this->event('order.placed', [
            'id'           => $order->id,
            'order_number' => $order->order_number,
            'user_id'      => $order->user_id,
            'total_amount' => $order->total_amount,
        ]);

        $this->session->setFlash('success', "Order #{$order->order_number} placed successfully!");
        $this->redirect("/order/success/{$order->id}");
    }

    public function success(string $id): string
    {
        $order = (new \App\Models\Order())->findInstance((int)$id);
        if (!$order) {
            $this->response->setStatusCode(404);
            return $this->render('error_404', ['message' => 'Order not found.']);
        }

        $items = (new \App\Models\Order())->items()->for($order);

        return $this->render('shop/order_success', [
            'title' => 'Order Confirmation',
            'order' => $order,
            'items' => $items,
        ]);
    }
}
