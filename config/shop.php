<?php

return [
    'related_products_limit' => 6,
    'complete_room_limit' => 4,
    'recently_viewed_limit' => 12,

    'seo' => [
        'site_name' => 'Furemir',
        'default_title' => 'Furemir Furniture',
        'default_description' => 'Curated furniture for modern homes, from living room and bedroom pieces to office, dining and decor essentials.',
        'default_image' => '/images/logo/logo.png',
        'organization_name' => 'Furemir',
        'organization_url' => '/',
        'search_path' => '/shop?search={search_term_string}',
    ],

    'complete_room_categories' => [
        'sofa' => ['Coffee Table', 'Rug', 'Lamp', 'Side Table'],
        'bed' => ['Bedside Table', 'Wardrobe', 'Lamp'],
        'dining table' => ['Dining Chair', 'Cabinet', 'Lamp'],
        'office chair' => ['Desk', 'Bookcase', 'Lamp'],
        'hanging chair' => ['Side Table', 'Rug', 'Lamp'],
        'folding chair' => ['Folding Table', 'Outdoor Table', 'Storage'],
        'chair' => ['Table', 'Desk', 'Rug'],
        'table' => ['Chair', 'Lamp', 'Cabinet'],
    ],

    'ai_interior_combo' => [
        'enabled' => true,
        'model' => 'furemir-rule-assisted',
        'max_products' => 5,
        'min_products' => 3,
        'candidate_limit' => 24,
        'budget_overage_percent' => 10,
        'room_category_mapping' => [
            'living room' => ['Sofa', 'Coffee Table', 'Side Table', 'Rug', 'Lamp', 'Cabinet', 'Bookcase'],
            'bedroom' => ['Bed', 'Bedside Table', 'Wardrobe', 'Lamp', 'Rug', 'Cabinet'],
            'dining room' => ['Dining Table', 'Dining Chair', 'Cabinet', 'Lamp', 'Bar Cart'],
            'office' => ['Office Chair', 'Desk', 'Bookcase', 'Lamp', 'Cabinet'],
        ],
    ],

    'catalog_quality' => [
        'min_images_per_product' => 2,
        'min_description_chars' => 90,
        'low_stock_threshold' => 5,
        'price_outlier_multiplier' => 3,
        'price_outlier_min_sample' => 3,
        'max_scan_products' => 100,
        'ai_batch_size' => 8,
        'ai_timeout_seconds' => 20,
        'allowed_apply_fields' => [
            'short_description',
            'description',
            'material',
        ],
    ],
];
