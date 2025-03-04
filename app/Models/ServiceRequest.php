<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'household_id',
        'company_id',
        'client_number',
        'waste_type',
        'quantity',
        'service_type',
        'preferred_date',
        'preferred_time',
        'address',
        'scheduled_date',
        'scheduled_time',
        'estimated_cost',
        'status',
        'description',
        'payment_amount',
        'payment_status',
        'final_amount',
        'payment_id',
        'completed_at',
        'payment_received_at',
        'completion_notes',
        'completion_photos',
        'company_user_id',
        'accepted_company_id',
        'household_completion_photos',
        'household_completion_notes',
        'waste_photos',
    ];
    protected $casts = [
        'payment_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'completed_at' => 'datetime',
        'payment_received_at' => 'datetime',
        'completion_photos' => 'json',
        'household_completion_photos' => 'json',
        'waste_photos' => 'json'
    ];
    

    public function companyBids()
{
    return $this->belongsToMany(Company::class, 'company_service_requests')
        ->withPivot('bid_amount', 'notes', 'status','company_id')
        ->withTimestamps(); // Access pivot fields like bid_amount, notes, and status
}

    
    // public function bids()
    // {
    //     return $this->hasManyThrough(
    //         Bid::class,
    //         CompanyServiceRequest::class,
    //         'service_request_id',
    //         'id',
    //         'id',
    //         'id'
    //     );
    // }

    public function household()
    {
        return $this->belongsTo(User::class, 'household_id');
    }
    public function payment()
    {
        return $this->hasOne(Payment::class, 'service_request_id');
    }
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function companyServiceRequest()
    {
        return $this->hasOne(CompanyServiceRequest::class, 'service_request_id');

    }
}
