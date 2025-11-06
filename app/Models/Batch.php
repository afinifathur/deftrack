<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Batch extends Model {
  protected $fillable=['heat_number','item_code','item_name','weight_per_pc','batch_qty','cast_date','import_session_id'];
  public function importSession(){return $this->belongsTo(ImportSession::class);}
  public function defectLines(){return $this->hasMany(DefectLine::class);}
}