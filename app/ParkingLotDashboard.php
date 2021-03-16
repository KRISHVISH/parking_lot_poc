<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParkingLotDashboard extends Model
{
    use HasFactory;
    protected $table = "parking_lot_dashboard";
    protected $guarded = [];
    public $timestamps = false;
}
