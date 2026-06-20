<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    use HasFactory;
    protected $table = 'order_details';
    protected $fillable = ['order_id', 'sku', 'quantities', 'unit_price'];
    protected $primaryKey = ['order_id', 'sku'];
    public $incrementing = false;

    public static function reviewKey(int|string $orderId, string $sku): string
    {
        return Review::orderDetailKey($orderId, $sku);
    }

    public function getReviewKeyAttribute(): string
    {
        return self::reviewKey($this->order_id, $this->sku);
    }

    public function detailed_product()
    {
        return $this->belongsTo(ProductDetail::class, 'sku');
    }
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function review()
    {
        return $this->hasOne(Review::class, 'order_detail_id', 'review_key');
    }
}
