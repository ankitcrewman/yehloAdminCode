<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
