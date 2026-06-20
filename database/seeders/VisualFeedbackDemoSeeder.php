<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VisualFeedback;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class VisualFeedbackDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('is_staff', false)->first()
            ?: User::firstOrCreate(
                ['email' => 'feedback.demo@furemir-demo.test'],
                [
                    'password' => Hash::make('123123'),
                    'first_name' => 'Feedback',
                    'last_name' => 'Demo',
                    'gender' => 1,
                    'is_verified' => true,
                    'is_active' => true,
                ],
            );

        $admin = User::where('is_staff', true)->first();

        $samples = [
            [
                'type' => 'Wrong image',
                'priority' => 'High',
                'title' => 'Demo: Product image does not match product name',
                'description' => 'The selected office chair is displaying a hanging chair image.',
                'expected_result' => 'Display the correct office chair image.',
                'page_url' => url('/shop'),
                'page_path' => '/shop',
                'page_title' => 'Shop',
                'element_tag' => 'img',
                'element_text' => '',
                'css_selector' => '[data-feedback-id="shop.product-image.demo"]',
            ],
            [
                'type' => 'Bug',
                'priority' => 'Critical',
                'title' => 'Demo: Add to cart button does not respond',
                'description' => 'Clicking Add to cart on a product card does not update the mini cart.',
                'expected_result' => 'The selected SKU should be added to cart and the mini cart count should update.',
                'page_url' => url('/shop'),
                'page_path' => '/shop',
                'page_title' => 'Shop',
                'element_tag' => 'button',
                'element_text' => 'Add to cart',
                'css_selector' => '[data-feedback-id="shop.add-to-cart.demo"]',
            ],
            [
                'type' => 'UI/UX',
                'priority' => 'Medium',
                'title' => 'Demo: Product card spacing is tight on mobile',
                'description' => 'On mobile, product cards feel cramped and the price is close to the title.',
                'expected_result' => 'Add comfortable spacing between product card sections on mobile.',
                'page_url' => url('/'),
                'page_path' => '/',
                'page_title' => 'Furemir',
                'element_tag' => 'div',
                'element_text' => 'Product card',
                'css_selector' => '[data-component="ProductCard"]',
            ],
            [
                'type' => 'Feature request',
                'priority' => 'Low',
                'title' => 'Demo: Add quick filter for best selling chairs',
                'description' => 'Customers should be able to filter quickly by best selling chair categories.',
                'expected_result' => 'Add a best selling shortcut in the shop filter area.',
                'page_url' => url('/shop'),
                'page_path' => '/shop',
                'page_title' => 'Shop',
                'element_tag' => 'div',
                'element_text' => 'Product Categories',
                'css_selector' => '[data-feedback-id="shop.filters"]',
            ],
            [
                'type' => 'Content',
                'priority' => 'Medium',
                'title' => 'Demo: Product material text is incomplete',
                'description' => 'The product material does not mention cushion fabric and frame finish.',
                'expected_result' => 'Show complete material details in product information.',
                'page_url' => url('/products/1'),
                'page_path' => '/products/1',
                'page_title' => 'Product detail',
                'element_tag' => 'div',
                'element_text' => 'Material',
                'css_selector' => '[data-feedback-id="product-detail.info"]',
            ],
        ];

        foreach ($samples as $sample) {
            $feedback = VisualFeedback::firstOrCreate(
                ['title' => $sample['title']],
                array_merge($sample, [
                    'user_id' => $user->getKey(),
                    'assigned_to' => $admin?->getKey(),
                    'status' => 'Open',
                    'created_by_role' => 'demo-user',
                    'element_attributes' => ['data-feedback-id' => $sample['css_selector']],
                    'element_rect' => ['x' => 120, 'y' => 220, 'width' => 320, 'height' => 180],
                    'scroll_position' => ['x' => 0, 'y' => 0],
                    'viewport' => ['width' => 1440, 'height' => 900],
                    'browser_info' => ['user_agent' => 'Demo seeder'],
                    'device_type' => 'desktop',
                ]),
            );

            $feedback->activities()->firstOrCreate([
                'action' => 'seeded_demo',
            ], [
                'user_id' => $admin?->getKey(),
                'new_value' => ['title' => $sample['title']],
            ]);
        }
    }
}
