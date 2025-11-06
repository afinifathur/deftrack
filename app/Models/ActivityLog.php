<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ActivityLog extends Model {
  protected $fillable=['user_id','role','ip','action','changes'];
  protected $casts=['changes'=>'array'];
}