<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Order extends Model
{
    use HasFactory;

    public const PAYMENT_METHOD_COD = 'cod';
    public const PAYMENT_METHOD_VNPAY = 'vnpay';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';
    public const PAYMENT_STATUS_PENDING = 'pending';
    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_FAILED = 'failed';
    public const PAYMENT_STATUS_CANCELLED = 'cancelled';
    public const PAYMENT_STATUS_REFUNDED = 'refunded';

    protected $table = 'orders';
    protected $primaryKey = 'order_id';
    protected $fillable = [
        'total_price',
        'is_paid',
        'payment_method',
        'payment_status',
        'paid_at',
        'status',
        'receiver_name',
        'address',
        'phone_number',
        'note',
        'customer_id',
        'created_by',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
    ];

    // status: 0: unconfirmed, 1: confirmed, 2: in transit, 3: delivered, 4: canceled
    public function get_status()
    {
        switch ($this->status) {
            case 0:
                return 'Awaiting Confirm';
            case 1:
                return 'Confirmed';
            case 2:
                return 'In transit';
            case 3:
                return 'Delivered';
            case 4:
                return 'Canceled';
            default:
                return 'Unknown';
        }
    }
    public function get_is_paid()
    {
        if ($this->payment_status === self::PAYMENT_STATUS_FAILED) {
            return 'Payment Failed';
        }

        if ($this->payment_status === self::PAYMENT_STATUS_CANCELLED) {
            return 'Payment Canceled';
        }

        if ($this->payment_status === self::PAYMENT_STATUS_PENDING) {
            return 'Payment Pending';
        }

        return $this->is_paid ? 'Payment Received' : 'Pending Payment';
    }
    public function order_details()
    {
        return $this->hasMany(OrderDetail::class, 'order_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'order_id', 'order_id');
    }

    public function latest_payment()
    {
        return $this->hasOne(Payment::class, 'order_id', 'order_id')->latestOfMany();
    }
    public function employee()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
    public function howmanydaysago()
    {
        return Carbon::parse($this->created_at)->diffForHumans();
    }
    public function money_type()
    {
        $money = number_format($this->total_price, 0, '.', ',');
        return $money;
    }
}
