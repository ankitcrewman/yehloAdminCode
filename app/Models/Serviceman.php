<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class Serviceman extends Model
{
    use HasFactory;

    // Define the table associated with the model
    protected $table = 'servicemen';

    // Specify the fields that are mass assignable
    protected $fillable = [
        'first_name',
        'last_name',
        'phone_number',
        'email',
        'serviceman_image',
        'document_type',
        'identity_number',
        'identification_image',
        'password',
        'store_id',
        'secondary_store_id',
        'module_type',
        'module_id',
        'status',
    ];

    // Define the relationship with the Store model
    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    // Define the relationship with the secondary store
    public function secondaryStore()
    {
        return $this->belongsTo(Store::class, 'secondary_store_id');
    }

    public function total_canceled_orders()
    {
        return $this->hasMany(Order::class)->where('order_status', 'canceled');
    }
    public function total_ongoing_orders()
    {
        return $this->hasMany(Order::class)->whereIn('order_status', ['handover', 'picked_up']);
    }

    public function userinfo()
    {
        return $this->hasOne(UserInfo::class, 'deliveryman_id', 'id');
    }

    public function vehicle()
    {
        return $this->belongsTo(DMVehicle::class);
    }

    public function wallet()
    {
        return $this->hasOne(DeliveryManWallet::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function order_transaction()
    {
        return $this->hasMany(OrderTransaction::class);
    }

    public function todays_earning()
    {
        return $this->hasMany(OrderTransaction::class)->whereDate('created_at', now());
    }

    public function this_week_earning()
    {
        return $this->hasMany(OrderTransaction::class)->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
    }

    public function this_month_earning()
    {
        return $this->hasMany(OrderTransaction::class)->whereMonth('created_at', date('m'))->whereYear('created_at', date('Y'));
    }

    public function todaysorders()
    {
        return $this->hasMany(Order::class)->whereDate('accepted', now());
    }

    public function total_delivered_orders()
    {
        return $this->hasMany(Order::class)->where('order_status', 'delivered');
    }

    public function this_week_orders()
    {
        return $this->hasMany(Order::class)->whereBetween('accepted', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
    }

    public function delivery_history()
    {
        return $this->hasMany(DeliveryHistory::class, 'delivery_man_id');
    }

    public function last_location()
    {
        return $this->hasOne(DeliveryHistory::class, 'delivery_man_id')->latestOfMany();
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function reviews()
    {
        return $this->hasMany(DMReview::class);
    }

    public function disbursement_method()
    {
        return $this->hasOne(DisbursementWithdrawalMethod::class)->where('is_default', 1);
    }

    public function rating()
    {
        return $this->hasMany(DMReview::class)
            ->select(DB::raw('avg(rating) average, count(delivery_man_id) rating_count, delivery_man_id'))
            ->groupBy('delivery_man_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', 1)->where('application_status', 'approved');
    }
    public function scopeInActive($query)
    {
        return $query->where('active', 0)->where('application_status', 'approved');
    }

    public function scopeEarning($query)
    {
        return $query->where('earning', 1);
    }

    public function scopeAvailable($query)
    {
        return $query->where('current_orders', '<', config('dm_maximum_orders') ?? 1);
    }

    public function scopeUnavailable($query)
    {
        return $query->where('current_orders', '>', config('dm_maximum_orders') ?? 1);
    }

    public function scopeZonewise($query)
    {
        return $query->where('type', 'zone_wise');
    }

    // protected static function booted()
    // {
    //     static::addGlobalScope(new ZoneScope);
    // }
}
